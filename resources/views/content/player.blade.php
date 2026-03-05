<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $name }} - BaseStream</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='15' fill='%23007BFF'/><text x='50' y='65' font-size='50' fill='white' text-anchor='middle'>📡</text></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: {
            'bs-dark': '#0f0a1a', 'bs-accent': '#a855f7',
        }}}}
    </script>

    {{-- HLS.js para reprodução de streams M3U8 --}}
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>

    <style>
        body { background: #000; margin: 0; }
        .player-container { position: relative; width: 100%; max-width: 100vw; background: #000; }
        video { width: 100%; height: auto; max-height: 90vh; background: #000; }
        .controls-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 1rem; opacity: 0; transition: opacity 0.3s;
        }
        .player-container:hover .controls-overlay { opacity: 1; }
        .loading-spinner {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            border: 4px solid rgba(168,85,247,0.3); border-top: 4px solid #a855f7;
            border-radius: 50%; width: 48px; height: 48px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
    </style>
</head>
<body class="text-white">

    {{-- Top bar --}}
    <div class="bg-black/80 backdrop-blur-md border-b border-purple-500/20 px-4 py-3 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <a href="javascript:history.back()" class="text-gray-400 hover:text-white transition-colors">
                ← Voltar
            </a>
            <span class="text-sm font-medium text-white truncate max-w-[200px] sm:max-w-md">{{ $name }}</span>
        </div>
        <a href="{{ route('content.index') }}" class="text-xs text-gray-500 hover:text-white">Início</a>
    </div>

    {{-- Player --}}
    <div class="player-container" id="playerContainer" x-data="{ loading: true, error: false, errorMsg: '' }">
        {{-- Loading --}}
        <div x-show="loading" class="loading-spinner" id="loadingSpinner"></div>

        {{-- Error overlay --}}
        <div x-show="error" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/90 z-10">
            <div class="text-center p-6">
                <p class="text-red-400 text-lg mb-2">Erro ao reproduzir</p>
                <p class="text-gray-500 text-sm mb-4" x-text="errorMsg"></p>
                <div class="flex gap-3 justify-center">
                    <button onclick="retryPlayback()" class="bg-purple-600 hover:bg-purple-500 px-4 py-2 rounded-lg text-sm">
                        Tentar novamente
                    </button>
                    <a href="javascript:history.back()" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm">
                        Voltar
                    </a>
                </div>
            </div>
        </div>

        <video id="videoPlayer" controls autoplay playsinline
               @if(!empty($thumbnail)) poster="{{ $thumbnail }}" @endif>
            Seu navegador não suporta vídeo HTML5.
        </video>
    </div>

    {{-- Info --}}
    <div class="max-w-4xl mx-auto px-4 py-6">
        <h2 class="text-xl font-bold text-white mb-2">{{ $name }}</h2>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>Tipo: {{ $stream['type'] ?? 'auto' }}</span>
            <span>•</span>
            <span id="streamQuality"></span>
        </div>

        {{-- Stream info debug (collapsed) --}}
        <details class="mt-4 text-xs text-gray-600">
            <summary class="cursor-pointer hover:text-gray-400">Info técnica</summary>
            <div class="mt-2 bg-black/50 rounded-lg p-3 font-mono break-all">
                <p>URL: {{ $stream['url'] ?? 'N/A' }}</p>
                <p>Proxy: {{ $proxyUrl }}</p>
                <p>Type: {{ $stream['type'] ?? 'auto' }}</p>
            </div>
        </details>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        const videoEl = document.getElementById('videoPlayer');
        const proxyUrl = @json($proxyUrl);
        const streamUrl = @json($stream['url'] ?? '');
        const streamType = @json($stream['type'] ?? 'auto');

        function getComponent() {
            return Alpine.$data(document.getElementById('playerContainer'));
        }

        function initPlayer() {
            const url = proxyUrl || streamUrl;
            const comp = getComponent();

            // Detecta se é HLS
            const isHls = streamType === 'hls' || url.includes('.m3u8') || streamUrl.includes('.m3u8');

            if (isHls && Hls.isSupported()) {
                const hls = new Hls({
                    debug: false,
                    enableWorker: true,
                    lowLatencyMode: false,
                    backBufferLength: 90,
                    maxBufferLength: 30,
                    maxMaxBufferLength: 60,
                    startLevel: -1, // auto quality
                    xhrSetup: function(xhr, url) {
                        xhr.withCredentials = false;
                    }
                });

                hls.loadSource(url);
                hls.attachMedia(videoEl);

                hls.on(Hls.Events.MANIFEST_PARSED, function(event, data) {
                    comp.loading = false;
                    videoEl.play().catch(() => {});

                    // Show quality info
                    const levels = hls.levels;
                    if (levels.length > 0) {
                        const best = levels[levels.length - 1];
                        document.getElementById('streamQuality').textContent =
                            `${best.width}x${best.height} • ${levels.length} qualidade(s)`;
                    }
                });

                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) {
                        comp.loading = false;
                        comp.error = true;
                        comp.errorMsg = 'Erro HLS: ' + (data.details || data.type);

                        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                            // Tenta recuperar
                            setTimeout(() => hls.startLoad(), 3000);
                        } else {
                            hls.destroy();
                        }
                    }
                });

                window._hls = hls;

            } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
                // Safari native HLS
                videoEl.src = url;
                videoEl.addEventListener('loadedmetadata', () => {
                    comp.loading = false;
                    videoEl.play().catch(() => {});
                });
            } else {
                // MP4 ou outro formato direto
                videoEl.src = url;
                videoEl.addEventListener('loadeddata', () => {
                    comp.loading = false;
                    videoEl.play().catch(() => {});
                });
            }

            videoEl.addEventListener('error', function() {
                comp.loading = false;
                comp.error = true;
                comp.errorMsg = 'Erro ao carregar o vídeo. Verifique a fonte.';
            });

            // Timeout de 30s
            setTimeout(() => {
                if (comp.loading) {
                    comp.loading = false;
                    comp.error = true;
                    comp.errorMsg = 'Timeout: stream demorou muito para responder.';
                }
            }, 30000);
        }

        function retryPlayback() {
            const comp = getComponent();
            comp.error = false;
            comp.loading = true;
            if (window._hls) {
                window._hls.destroy();
                window._hls = null;
            }
            initPlayer();
        }

        // Wait for Alpine
        document.addEventListener('alpine:init', () => {
            setTimeout(initPlayer, 100);
        });
    </script>

</body>
</html>
