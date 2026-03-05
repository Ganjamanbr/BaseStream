@extends('tv.layout', ['pageTitle' => $title ?? 'Player', 'hideNav' => true, 'hideRemoteHint' => true])

@push('styles')
<style>
/* ─── Player fullscreen overlay ─── */
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

/* ─── OSD overlay ─── */
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

/* ─── Loading / error ─── */
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
        <div id="osd-status">▶ Reproduzindo</div>
    </div>
    <div id="osd-keys">
        ⏎ Play/Pause &nbsp;|&nbsp; ← Voltar<br>
        ⏩ Avançar &nbsp;|&nbsp; ⏪ Retroceder
    </div>
</div>

<div id="player-loading">
    <div class="spinner"></div>
    <div class="loading-title">Carregando…</div>
    <div class="loading-sub">{{ $title ?? '' }}</div>
</div>

<div id="player-error">
    <div id="error-icon">⚠️</div>
    <div id="error-msg">Erro ao reproduzir</div>
    <div id="error-sub" id="error-detail"></div>
    <button id="error-back" data-focusable tabindex="0" onclick="history.back()">← Voltar</button>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
<script>
(function () {
    'use strict';

    var streamLink = @json($link ?? '');
    var streamTitle = @json($title ?? '');
    var csrfToken  = @json(csrf_token());

    var video      = document.getElementById('player-video');
    var iframe     = document.getElementById('player-iframe');
    var loadingEl  = document.getElementById('player-loading');
    var errorEl    = document.getElementById('player-error');
    var osd        = document.getElementById('osd');
    var osdStatus  = document.getElementById('osd-status');
    var hls        = null;
    var osdTimer   = null;
    var paused     = false;

    // ─── OSD ─────────────────────────────────────────────────────────────────
    function showOsd() {
        osd.classList.add('visible');
        clearTimeout(osdTimer);
        osdTimer = setTimeout(function () { osd.classList.remove('visible'); }, 4000);
    }

    // ─── States ──────────────────────────────────────────────────────────────
    function showLoading() { loadingEl.style.display = 'flex'; errorEl.style.display = 'none'; }
    function hideLoading() { loadingEl.style.display = 'none'; }
    function showError(msg) {
        hideLoading();
        document.getElementById('error-detail').textContent = msg || '';
        errorEl.style.display = 'flex';
        var btn = document.getElementById('error-back');
        if (btn) { btn.focus(); }
    }

    // ─── Resolve stream via backend ──────────────────────────────────────────
    function resolveStream() {
        showLoading();
        fetch('{{ route('tv.resolve') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ link: streamLink })
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (data.error) { showError(data.error); return; }
            if (data.type === 'iframe') {
                startIframe(data.url);
            } else {
                startHls(data.url, data.headers || {});
            }
        })
        .catch(function (err) {
            showError('Falha ao resolver stream: ' + err.message);
        });
    }

    // ─── HLS player ──────────────────────────────────────────────────────────
    function startHls(url, headers) {
        hideLoading();
        video.style.display = 'block';

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
                video.play();
                showOsd();
            });
            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    showError('HLS erro: ' + data.type);
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / Tizen native HLS
            video.src = url;
            video.addEventListener('loadedmetadata', function () {
                video.play();
                showOsd();
            });
            video.addEventListener('error', function () {
                showError('Falha ao reproduzir stream.');
            });
        } else {
            showError('Este dispositivo não suporta HLS.');
        }

        video.addEventListener('pause', function () {
            paused = true; osdStatus.textContent = '⏸ Pausado'; showOsd();
        });
        video.addEventListener('play', function () {
            paused = false; osdStatus.textContent = '▶ Reproduzindo'; showOsd();
        });
    }

    // ─── iframe player ───────────────────────────────────────────────────────
    function startIframe(url) {
        hideLoading();
        iframe.style.display = 'block';
        iframe.src = url;
        showOsd();
    }

    // ─── Remote / keyboard control ───────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        var code = e.keyCode;

        // BACK
        if (code === 8 || code === 10009 || code === 27) {
            e.preventDefault();
            if (hls) { hls.destroy(); }
            history.back();
            return;
        }

        // PLAY/PAUSE toggle (ENTER on video, or media keys)
        if (code === 13 || code === 415 || code === 19) {
            e.preventDefault();
            if (video.style.display !== 'none') {
                if (video.paused) { video.play(); } else { video.pause(); }
            }
            showOsd();
            return;
        }

        // STOP
        if (code === 413) {
            e.preventDefault();
            if (hls) { hls.destroy(); }
            history.back();
            return;
        }

        // FAST-FORWARD
        if (code === 417) {
            e.preventDefault();
            if (video.style.display !== 'none') { video.currentTime += 15; }
            showOsd();
            return;
        }

        // REWIND
        if (code === 412) {
            e.preventDefault();
            if (video.style.display !== 'none') { video.currentTime -= 15; }
            showOsd();
            return;
        }

        // Any other key: show OSD
        showOsd();
    }, true); // capture phase — intercept before layout.blade

    // ─── Init ────────────────────────────────────────────────────────────────
    if (streamLink) {
        resolveStream();
    } else {
        showError('Nenhum link de stream fornecido.');
    }
})();
</script>
@endpush
