<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Services\VideoCodecService;

/**
 * Live HLS transcoding via FFmpeg.
 *
 * Flow:
 *   1. POST /api/transcode/start   → starts FFmpeg, returns {hash, playlist_url}
 *   2. GET  /stream/hls/{h}/playlist.m3u8  → serves live HLS playlist
 *   3. GET  /stream/hls/{h}/{seg}.ts       → serves HLS segment
 *   4. DELETE /api/transcode/{h}   → stops FFmpeg, cleans up
 *
 * Storage: storage/app/transcode/{hash}/  (auto-cleaned after TTL)
 */
class TranscoderController extends Controller
{
    /** Session idle TTL — transcode dirs older than this get deleted */
    private const TTL_MINUTES = 15;

    /** How long to wait for the first .ts segment before giving up */
    private const BOOT_TIMEOUT_S = 12;

    /** FFmpeg preset — "veryfast" is a good balance of quality vs CPU */
    private const PRESET = 'veryfast';

    public function __construct(
        private readonly VideoCodecService $codec
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // START — POST /api/transcode/start
    // ─────────────────────────────────────────────────────────────────────────

    public function start(Request $request): JsonResponse
    {
        // Auth — accept TV session or Bearer API token
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $url = $request->input('url', '');
        if (empty($url)) {
            return response()->json(['error' => 'URL ausente'], 400);
        }

        // Clean up old sessions
        $this->gc();

        // Unique hash for this transcode session
        $hash = md5($url . session()->getId() . microtime());

        $dir = $this->transcodeDir($hash);
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return response()->json(['error' => 'Falha ao criar diretório temporário'], 500);
        }

        // Write source URL for debugging/monitoring
        file_put_contents($dir . '/.url', $url);
        file_put_contents($dir . '/.started', time());

        // Build FFmpeg command
        $playlistPath = $dir . '/playlist.m3u8';
        $segPattern   = $dir . '/%05d.ts';

        $cmd = $this->buildFfmpegCommand($url, $playlistPath, $segPattern);

        Log::info("Transcode start [{$hash}]: {$cmd}");

        // Launch FFmpeg as background process
        $pidFile = $dir . '/.pid';
        $logFile = $dir . '/.ffmpeg.log';

        $fullCmd = "{$cmd} > " . escapeshellarg($logFile) . " 2>&1 & echo \$! > " . escapeshellarg($pidFile);
        exec($fullCmd);

        // Wait for first segment (up to BOOT_TIMEOUT_S)
        $booted = $this->waitForBoot($dir);

        if (!$booted) {
            $ffLog = @file_get_contents($logFile);
            Log::warning("Transcode boot timeout [{$hash}]: " . substr($ffLog ?? '', -500));
            $this->cleanup($hash);
            return response()->json([
                'error' => 'Timeout ao iniciar transcoding. O stream pode estar indisponível ou em formato incompatível.',
                'detail' => trim(substr($ffLog ?? '', -200)),
            ], 504);
        }

        $playlistUrl = url("/stream/hls/{$hash}/playlist.m3u8");

        return response()->json([
            'hash'         => $hash,
            'playlist_url' => $playlistUrl,
            'type'         => 'hls',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SERVE PLAYLIST — GET /stream/hls/{hash}/playlist.m3u8
    // ─────────────────────────────────────────────────────────────────────────

    public function playlist(string $hash): Response
    {
        if (!$this->validHash($hash)) {
            abort(403);
        }

        $path = $this->transcodeDir($hash) . '/playlist.m3u8';

        if (!file_exists($path)) {
            abort(404, 'Playlist not ready');
        }

        // Update last-accessed timestamp
        touch($this->transcodeDir($hash) . '/.accessed');

        $content = file_get_contents($path);

        // Rewrite segment URLs to go through our controller
        // FFmpeg writes relative segment names; HLS.js needs absolute-or-relative URLs
        // We leave them relative — the HLS.js base URL will resolve them correctly.

        return response($content, 200, [
            'Content-Type'  => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SERVE SEGMENT — GET /stream/hls/{hash}/{segment}.ts
    // ─────────────────────────────────────────────────────────────────────────

    public function segment(string $hash, string $segment): Response
    {
        if (!$this->validHash($hash)) {
            abort(403);
        }

        // Only allow [digits].ts
        if (!preg_match('/^\d{5}$/', $segment)) {
            abort(400, 'Invalid segment');
        }

        $path = $this->transcodeDir($hash) . "/{$segment}.ts";

        // Wait briefly for segment if it's not yet written
        $waited = 0;
        while (!file_exists($path) && $waited < 6) {
            usleep(500_000); // 0.5s
            $waited += 0.5;
        }

        if (!file_exists($path)) {
            abort(404, 'Segment not ready');
        }

        touch($this->transcodeDir($hash) . '/.accessed');

        return response(file_get_contents($path), 200, [
            'Content-Type'  => 'video/mp2t',
            'Cache-Control' => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STOP — DELETE /api/transcode/{hash}
    // ─────────────────────────────────────────────────────────────────────────

    public function stop(Request $request, string $hash): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $this->cleanup($hash);
        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROBE — POST /api/transcode/probe
    // Returns codec info for a stream URL without starting transcoding.
    // ─────────────────────────────────────────────────────────────────────────

    public function probe(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $url = $request->input('url', '');
        if (empty($url)) {
            return response()->json(['error' => 'URL ausente'], 400);
        }

        $info = $this->codec->probe($url, timeout: 8);

        return response()->json($info);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildFfmpegCommand(string $url, string $playlist, string $segPattern): string
    {
        return implode(' ', [
            'ffmpeg',
            '-reconnect 1',
            '-reconnect_streamed 1',
            '-reconnect_delay_max 5',
            '-timeout 10000000',               // 10s connect timeout (μs)
            '-re',                             // realtime speed (for live streams)
            '-i ' . escapeshellarg($url),
            '-c:v libx264',
            '-preset ' . self::PRESET,
            '-tune zerolatency',
            '-crf 23',
            '-maxrate 4000k',
            '-bufsize 8000k',
            '-profile:v high',
            '-level:v 4.1',
            '-pix_fmt yuv420p',               // widest compatibility (Tizen)
            '-c:a aac',
            '-b:a 128k',
            '-ar 44100',
            '-ac 2',                           // stereo
            '-f hls',
            '-hls_time 3',
            '-hls_list_size 10',
            '-hls_flags delete_segments+append_list+omit_endlist',
            '-hls_segment_filename ' . escapeshellarg($segPattern),
            escapeshellarg($playlist),
        ]);
    }

    private function waitForBoot(string $dir): bool
    {
        $start   = microtime(true);
        $timeout = self::BOOT_TIMEOUT_S;

        while ((microtime(true) - $start) < $timeout) {
            // Check for any .ts segment
            $segments = glob($dir . '/*.ts');
            if (!empty($segments)) {
                return true;
            }
            usleep(500_000); // 0.5s poll
        }
        return false;
    }

    private function transcodeDir(string $hash): string
    {
        return storage_path('app/transcode/' . preg_replace('/[^a-f0-9]/', '', $hash));
    }

    private function validHash(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $hash);
    }

    private function cleanup(string $hash): void
    {
        if (!$this->validHash($hash)) return;

        $dir = $this->transcodeDir($hash);

        // Kill FFmpeg
        $pidFile = $dir . '/.pid';
        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));
            if ($pid > 0) {
                posix_kill($pid, SIGTERM);
                // Give it 2 seconds, then SIGKILL
                sleep(1);
                if (file_exists("/proc/{$pid}")) {
                    posix_kill($pid, SIGKILL);
                }
            }
        }

        // Remove directory
        if (is_dir($dir)) {
            $files = glob($dir . '/*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            $hidden = glob($dir . '/.*') ?: [];
            foreach ($hidden as $file) {
                if (!in_array(basename($file), ['.', '..'])) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }

        Log::info("Transcode cleanup [{$hash}]");
    }

    /**
     * Garbage collect expired sessions (older than TTL_MINUTES since last access).
     */
    private function gc(): void
    {
        $baseDir = storage_path('app/transcode');
        if (!is_dir($baseDir)) return;

        $dirs = glob($baseDir . '/*', GLOB_ONLYDIR) ?: [];
        $now  = time();
        $ttl  = self::TTL_MINUTES * 60;

        foreach ($dirs as $dir) {
            $hash = basename($dir);
            if (!preg_match('/^[a-f0-9]{32}$/', $hash)) continue;

            $accessFile  = $dir . '/.accessed';
            $startedFile = $dir . '/.started';

            $lastTs = file_exists($accessFile)
                ? (int) file_get_contents($accessFile)
                : (file_exists($startedFile) ? (int) file_get_contents($startedFile) : 0);

            if ($lastTs > 0 && ($now - $lastTs) > $ttl) {
                $this->cleanup($hash);
            }
        }
    }

    /**
     * Auth check — accepts TV session OR Bearer API token.
     */
    private function authorized(Request $request): bool
    {
        // Check TV session
        $userId = $request->session()->get('tv_user_id');
        if ($userId) return true;

        // Check Bearer token (for API clients)
        $bearer = $request->bearerToken();
        if ($bearer) {
            $token = \App\Models\ApiToken::where('token', hash('sha256', $bearer))
                ->where('is_active', true)
                ->first();
            if ($token) return true;
        }

        // Check authenticated web session
        if (auth()->check()) return true;

        return false;
    }
}
