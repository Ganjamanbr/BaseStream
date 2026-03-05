@extends('tv.layout', ['pageTitle' => $title ?? 'Player', 'hideNav' => true, 'hideRemoteHint' => true])

@push('styles')
<style>
/* â”€â”€â”€ Player fullscreen overlay â”€â”€â”€ */
body { background: #000 !important; }

.player-wrap {
    position: fixed;
    inset: 0;
    background: #000;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}

#player-video,
#player-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: none;
    background: #000;
}

/* â”€â”€â”€ OSD overlay â”€â”€â”€ */
#osd {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    padding: 4rem 6rem 4rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.9));
    display: flex;
    align-items: flex-end;
    gap: 2rem;
    z-index: 200;
    opacity: 0;
    transition: opacity 0.4s;
    pointer-events: none;
}

#osd.visible { opacity: 1; }

#osd-thumb {
    width: 12rem;
    aspect-ratio: 16/9;
    object-fit: cover;
    border-radius: 1rem;
    border: 2px solid rgba(255,255,255,0.25);
    flex-shrink: 0;
}

#osd-info { flex: 1; }

#osd-title {
    font-size: var(--font-md);
    font-weight: 800;
    color: #fff;
    margin-bottom: 0.8rem;
}

#osd-status {
    font-size: var(--font-sm);
    color: rgba(255,255,255,0.7);
}

#osd-keys {
    font-size: 1.6rem;
    color: rgba(255,255,255,0.55);
    text-align: right;
    flex-shrink: 0;
}

/* â”€â”€â”€ Loading / error â”€â”€â”€ */
#player-loading,
#player-error {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3rem;
    background: #000;
    color: #fff;
    z-index: 300;
}

#player-loading { display: flex; }
#player-error   { display: none; }

.spinner {
    width: 8rem;
    height: 8rem;
    border: 0.6rem solid rgba(255,255,255,0.15);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.loading-title { font-size: var(--font-md); font-weight: 700; }
.loading-sub   { font-size: var(--font-sm); color: rgba(255,255,255,0.55); }

#error-icon  { font-size: 7rem; }
#error-msg   { font-size: var(--font-md); font-weight: 700; }
#error-sub   { font-size: var(--font-sm); color: rgba(255,255,255,0.6); }
#error-back  {
    margin-top: 1rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 1rem;
    padding: 1.2rem 4rem;
    font-size: var(--font-sm);
    font-weight: 700;
    cursor: none;
    outline: none;
}
#error-back:focus { box-shadow: 0 0 0 3px var(--focus); }
</style>
@endpush

@section('content')
<div class="player-wrap">
    <video id="player-video" playsinline></video>
    <iframe id="player-iframe" allowfullscreen allow="autoplay; fullscreen"></iframe>
</div>

<div id="osd">
    @if(isset($thumb) && $thumb)
        <img id="osd-thumb" src="{{ $thumb }}" alt="">
    @endif
    <div id="osd-info">
        <div id="osd-title">{{ $title ?? 'Reproduzindo' }}</div>
        <div id="osd-status">â–¶ Reproduzindo</div>
        <div id="osd-codec" style="font-size:1.4rem;color:rgba(255,255,255,.45);margin-top:.4rem;"></div>
    </div>
    <div id="osd-keys">
        âŽ Play/Pause &nbsp;|&nbsp; â† Voltar<br>
        â© AvanÃ§ar &nbsp;|&nbsp; âª Retroceder
    </div>
</div>

<div id="player-loading">
    <div class="spinner"></div>
    <div class="loading-title" id="loading-title">Carregandoâ€¦</div>
    <div class="loading-sub">{{ $title ?? '' }}</div>
    <div class="loading-sub" id="loading-codec" style="color:rgba(255,255,255,.35);font-size:1.4rem;"></div>
</div>

<div id="player-error">
    <div id="error-icon">âš ï¸</div>
    <div id="error-msg">Erro ao reproduzir</div>
    <div id="error-sub" id="error-detail"></div>
    <button id="error-back" data-focusable tabindex="0" onclick="history.back()">â† Voltar</button>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
<script>
(function () {
    'use strict';

    var streamLink   = @json($link ?? '');
    var csrfToken    = @json(csrf_token());

    var video        = document.getElementById('player-video');
    var iframe       = document.getElementById('player-iframe');
    var loadingEl    = document.getElementById('player-loading');
    var loadingTitle = document.getElementById('loading-title');
    var loadingCodec = document.getElementById('loading-codec');
    var errorEl      = document.getElementById('player-error');
    var osd          = document.getElementById('osd');
    var osdStatus    = document.getElementById('osd-status');
    var osdCodec     = document.getElementById('osd-codec');

    var hls             = null;
    var osdTimer        = null;
    var transcodeHash   = null;   // active transcode session
    var transcodeRetried = false; // prevent infinite retry

    // â”€â”€â”€ OSD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function showOsd() {
        osd.classList.add('visible');
        clearTimeout(osdTimer);
        osdTimer = setTimeout(function () { osd.classList.remove('visible'); }, 4500);
    }

    // â”€â”€â”€ States â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function showLoading(msg) {
        if (msg) loadingTitle.textContent = msg;
        loadingEl.style.display = 'flex';
        errorEl.style.display   = 'none';
    }
    function hideLoading() { loadingEl.style.display = 'none'; }
    function showError(msg, detail) {
        hideLoading();
        document.getElementById('error-msg').textContent = msg || 'Erro ao reproduzir';
        document.getElementById('error-detail').textContent = detail || '';
        errorEl.style.display = 'flex';
        var btn = document.getElementById('error-back');
        if (btn) btn.focus();
    }

    function setCodecBadge(codec, transcoded) {
        if (osdCodec) {
            var label = codec ? codec.toUpperCase() : '';
            osdCodec.textContent = transcoded
                ? 'ðŸ”„ Transcoded â†’ H.264  |  original: ' + label
                : (label ? 'ðŸŽ¬ ' + label : '');
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 1. RESOLVE â€” get stream URL from backend
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function resolveStream() {
        showLoading('Resolvendo streamâ€¦');

        fetch('{{ route('tv.resolve') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ link: streamLink })
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (data.error) { showError('Stream indisponÃ­vel', data.error); return; }

            if (data.type === 'iframe') {
                startIframe(data.url);
                return;
            }

            // HLS â€” check codec
            var needsTranscode = data.needs_transcode === true;
            var codec          = data.codec || 'unknown';

            if (codec !== 'unknown') {
                loadingCodec.textContent = 'Codec: ' + codec.toUpperCase();
            }

            if (needsTranscode && !transcodeRetried) {
                loadingCodec.textContent = 'âš  ' + codec.toUpperCase() + ' â†’ transcoding para H.264â€¦';
                startTranscode(data.url, codec);
            } else {
                startHls(data.url, data.headers || {}, codec, false);
            }
        })
        .catch(function (err) {
            showError('Falha ao resolver stream', err.message);
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2. TRANSCODE â€” start FFmpeg on server
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function startTranscode(url, originalCodec) {
        showLoading('Iniciando transcodingâ€¦');
        loadingCodec.textContent = originalCodec
            ? originalCodec.toUpperCase() + ' â†’ H.264 (aguarde ~5s)'
            : 'Convertendo para H.264â€¦';

        fetch('{{ url('/api/transcode/start') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ url: url })
        })
        .then(function (r) {
            if (!r.ok) return r.json().then(function (d) { throw new Error(d.error || 'HTTP ' + r.status); });
            return r.json();
        })
        .then(function (data) {
            if (data.error) { showError('Transcode falhou', data.error); return; }
            transcodeHash = data.hash;
            loadingCodec.textContent = 'Transcoding ativo  |  convertendo ' + (originalCodec || '').toUpperCase() + ' â†’ H.264';
            startHls(data.playlist_url, {}, originalCodec, true);
        })
        .catch(function (err) {
            showError(
                'Falha no transcoding',
                'O stream ' + (originalCodec || '') + ' nÃ£o pÃ´de ser convertido: ' + err.message
            );
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3. HLS PLAYER
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function startHls(url, headers, codec, isTranscoded) {
        // Destroy any existing HLS instance
        if (hls) { hls.destroy(); hls = null; }
        video.style.display = 'block';
        iframe.style.display = 'none';

        setCodecBadge(codec, isTranscoded);

        if (Hls.isSupported()) {
            hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                xhrSetup: function (xhr) {
                    Object.keys(headers).forEach(function (k) {
                        xhr.setRequestHeader(k, headers[k]);
                    });
                }
            });

            hls.loadSource(url);
            hls.attachMedia(video);

            hls.on(Hls.Events.MANIFEST_PARSED, function () {
                hideLoading();
                video.play();
                showOsd();
            });

            hls.on(Hls.Events.ERROR, function (event, data) {
                if (!data.fatal) return;

                var isCodecError = data.details === Hls.ErrorDetails.BUFFER_INCOMPATIBLE_CODECS_ERROR
                    || data.details === 'bufferIncompatibleCodecsError'
                    || (data.type === Hls.ErrorTypes.MEDIA_ERROR && data.details && data.details.includes('Codec'));

                // Auto-fallback to transcode on first codec error
                if (isCodecError && !transcodeRetried && !isTranscoded) {
                    transcodeRetried = true;
                    hls.destroy(); hls = null;
                    video.style.display = 'none';
                    loadingCodec.textContent = 'âš  Codec incompatÃ­vel â€” ativando transcodingâ€¦';
                    showLoading('Ativando transcoding (codec incompatÃ­vel)â€¦');
                    startTranscode(url, codec);
                    return;
                }

                showError(
                    'Erro HLS: ' + data.type,
                    data.details || ''
                );
            });

        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / Tizen native HLS
            video.src = url;
            video.addEventListener('loadedmetadata', function () {
                hideLoading();
                video.play();
                showOsd();
            }, { once: true });
            video.addEventListener('error', function () {
                showError('Falha ao reproduzir stream nativo.');
            }, { once: true });
        } else {
            showError('Este dispositivo nÃ£o suporta HLS.');
        }

        video.addEventListener('pause', function () {
            osdStatus.textContent = 'â¸ Pausado'; showOsd();
        });
        video.addEventListener('play', function () {
            osdStatus.textContent = 'â–¶ Reproduzindo'; showOsd();
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 4. IFRAME PLAYER
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function startIframe(url) {
        hideLoading();
        iframe.style.display = 'block';
        iframe.src = url;
        showOsd();
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 5. CLEANUP â€” stop transcode session on exit
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function stopTranscodeSession() {
        if (!transcodeHash) return;
        var hash = transcodeHash;
        transcodeHash = null;
        // Use sendBeacon for reliable unload delivery
        var url = '/api/transcode/' + hash;
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url + '?_method=DELETE', '');
        } else {
            // Fallback: sync XHR
            var xhr = new XMLHttpRequest();
            xhr.open('DELETE', url, false);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            try { xhr.send(); } catch (e) {}
        }
    }

    window.addEventListener('unload',   stopTranscodeSession);
    window.addEventListener('pagehide', stopTranscodeSession);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6. REMOTE / KEYBOARD CONTROL
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.addEventListener('keydown', function (e) {
        var code = e.keyCode;

        // BACK / ESC
        if (code === 8 || code === 10009 || code === 27) {
            e.preventDefault();
            if (hls) hls.destroy();
            stopTranscodeSession();
            history.back();
            return;
        }

        // PLAY / PAUSE
        if (code === 13 || code === 415 || code === 19) {
            e.preventDefault();
            if (video.style.display !== 'none') {
                video.paused ? video.play() : video.pause();
            }
            showOsd();
            return;
        }

        // STOP
        if (code === 413) {
            e.preventDefault();
            if (hls) hls.destroy();
            stopTranscodeSession();
            history.back();
            return;
        }

        // FAST-FORWARD (+15s)
        if (code === 417) {
            e.preventDefault();
            if (video.style.display !== 'none') video.currentTime += 15;
            osdStatus.textContent = 'â© +15s';
            showOsd();
            return;
        }

        // REWIND (-15s)
        if (code === 412) {
            e.preventDefault();
            if (video.style.display !== 'none') video.currentTime -= 15;
            osdStatus.textContent = 'âª -15s';
            showOsd();
            return;
        }

        showOsd();
    }, true);

    // â”€â”€â”€ Init â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (streamLink) {
        resolveStream();
    } else {
        showError('Nenhum link de stream fornecido.');
    }
})();
</script>
@endpush