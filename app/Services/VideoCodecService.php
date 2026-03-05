<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Detects stream video codec via ffprobe.
 * Determines whether server-side transcoding is needed.
 */
class VideoCodecService
{
    /**
     * Codecs natively supported in modern browsers + Tizen 4+ web apps.
     * Anything NOT in this list will be transcoded.
     */
    private const SUPPORTED_VIDEO_CODECS = [
        'h264', 'avc', 'avc1',
        'vp8', 'vp9',
        'theora',
    ];

    /**
     * Audio codecs that need conversion to AAC for broad compatibility.
     */
    private const UNSUPPORTED_AUDIO_CODECS = [
        'ac3', 'eac3', 'truehd', 'dts', 'flac',
    ];

    /**
     * Probe a stream URL and return codec information.
     *
     * @param  string $url  Stream URL (HLS, MPEG-TS, MP4…)
     * @param  int    $timeout  Probe timeout in seconds
     * @return array{
     *     video_codec: string,
     *     audio_codec: string,
     *     width: int,
     *     height: int,
     *     bitrate: int,
     *     needs_transcode: bool,
     *     reason: string,
     *     error: string|null
     * }
     */
    public function probe(string $url, int $timeout = 10): array
    {
        $cacheKey = 'codec_probe_' . md5($url);

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($url, $timeout) {
            return $this->runProbe($url, $timeout);
        });
    }

    /**
     * Quick check — returns true if stream needs server-side transcoding.
     */
    public function needsTranscode(string $url): bool
    {
        $info = $this->probe($url);
        return $info['needs_transcode'] ?? false;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function runProbe(string $url, int $timeout): array
    {
        $default = [
            'video_codec'    => 'unknown',
            'audio_codec'    => 'unknown',
            'width'          => 0,
            'height'         => 0,
            'bitrate'        => 0,
            'needs_transcode'=> false,
            'reason'         => '',
            'error'          => null,
        ];

        if (!$this->ffprobeAvailable()) {
            return array_merge($default, ['error' => 'ffprobe not found']);
        }

        $cmd = sprintf(
            'ffprobe -v quiet -print_format json -show_streams' .
            ' -probesize 2M -analyzeduration 3000000' .
            ' -timeout %d000000' .
            ' %s 2>&1',
            $timeout,
            escapeshellarg($url)
        );

        $output = shell_exec($cmd);

        if (empty($output)) {
            return array_merge($default, ['error' => 'ffprobe returned no output']);
        }

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data['streams'])) {
            Log::debug("VideoCodecService ffprobe bad output: " . substr($output, 0, 300));
            return array_merge($default, ['error' => 'ffprobe parse error']);
        }

        $videoStream = null;
        $audioStream = null;

        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video' && $videoStream === null) {
                $videoStream = $stream;
            }
            if ($stream['codec_type'] === 'audio' && $audioStream === null) {
                $audioStream = $stream;
            }
        }

        $videoCodec = strtolower($videoStream['codec_name'] ?? 'unknown');
        $audioCodec = strtolower($audioStream['codec_name'] ?? 'unknown');
        $width       = (int) ($videoStream['width'] ?? 0);
        $height      = (int) ($videoStream['height'] ?? 0);
        $bitrate     = (int) ($data['format']['bit_rate'] ?? 0);

        $needsTranscode = false;
        $reason         = '';

        // Check video codec compatibility
        $videoBase = preg_replace('/[^a-z0-9]/', '', $videoCodec); // strip separators
        $supportedMatch = false;
        foreach (self::SUPPORTED_VIDEO_CODECS as $supported) {
            if (str_contains($videoBase, preg_replace('/[^a-z0-9]/', '', $supported))) {
                $supportedMatch = true;
                break;
            }
        }

        if (!$supportedMatch && $videoCodec !== 'unknown') {
            $needsTranscode = true;
            $reason = "video codec '{$videoCodec}' not supported";
        }

        // Check audio codec
        foreach (self::UNSUPPORTED_AUDIO_CODECS as $unsupported) {
            if (str_contains($audioCodec, $unsupported)) {
                $needsTranscode = true;
                $reason .= ($reason ? '; ' : '') . "audio codec '{$audioCodec}' not supported";
                break;
            }
        }

        return [
            'video_codec'     => $videoCodec,
            'audio_codec'     => $audioCodec,
            'width'           => $width,
            'height'          => $height,
            'bitrate'         => $bitrate,
            'needs_transcode' => $needsTranscode,
            'reason'          => $reason,
            'error'           => null,
        ];
    }

    private function ffprobeAvailable(): bool
    {
        return !empty(shell_exec('which ffprobe 2>/dev/null'));
    }
}
