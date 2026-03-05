@extends('dashboard.layout')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">📡 Guia VLC / IPTV Player</h1>
            <p class="text-gray-400 text-sm mt-1">Passo a passo para assistir pelo VLC, IPTV Smarters ou outros players</p>
        </div>
    </div>

    {{-- ─── PASSO 1: Criar Token ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-purple-600 text-white font-bold text-lg">1</span>
            <h2 class="text-xl font-semibold text-white">Criar um Token de Acesso</h2>
        </div>

        <p class="text-gray-300">
            Primeiro você precisa de um <strong class="text-purple-300">token</strong> para autenticar seu player.
            Vá na página <a href="{{ route('dashboard.tokens') }}" class="text-blue-400 hover:underline">Devices & Tokens</a>,
            crie um token com nome descritivo (ex: <span class="text-purple-300">"VLC PC"</span>, <span class="text-purple-300">"TV Samsung"</span>).
        </p>

        <div class="bg-black/40 rounded-lg p-4 border border-gray-700">
            <p class="text-gray-400 text-xs mb-2 uppercase tracking-wider">Exemplo de token gerado:</p>
            <code class="text-green-400 text-sm break-all">bs_aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5aB6cD7eF8g</code>
        </div>

        <div class="flex items-start space-x-2 text-yellow-300/80 text-sm bg-yellow-500/10 rounded-lg p-3 border border-yellow-500/20">
            <span class="text-lg">⚠️</span>
            <p><strong>Importante:</strong> Copie e salve o token ao criar! Ele não será exibido novamente por segurança.</p>
        </div>
    </div>

    {{-- ─── PASSO 2: Montar a URL ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-purple-600 text-white font-bold text-lg">2</span>
            <h2 class="text-xl font-semibold text-white">Escolher a Playlist</h2>
        </div>

        <p class="text-gray-300">
            Substitua <code class="text-purple-300 bg-purple-500/20 px-2 py-0.5 rounded">SEU_TOKEN</code> pelo token que você criou:
        </p>

        <div class="space-y-3">
            {{-- TV ao Vivo --}}
            <div class="bg-black/40 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-blue-300">📺 TV ao Vivo (todos os canais)</span>
                    <button onclick="copyUrl('url-tv')" class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded bg-gray-700/50 hover:bg-gray-600/50 transition">
                        📋 Copiar
                    </button>
                </div>
                <code id="url-tv" class="text-green-400 text-xs sm:text-sm break-all select-all">{{ url('/api/playlist/tv.m3u') }}?token=SEU_TOKEN</code>
            </div>

            {{-- Filmes --}}
            <div class="bg-black/40 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-blue-300">🎬 Filmes (Lançamentos)</span>
                    <button onclick="copyUrl('url-filmes')" class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded bg-gray-700/50 hover:bg-gray-600/50 transition">
                        📋 Copiar
                    </button>
                </div>
                <code id="url-filmes" class="text-green-400 text-xs sm:text-sm break-all select-all">{{ url('/api/playlist/filmes.m3u') }}?token=SEU_TOKEN</code>
            </div>

            {{-- Séries --}}
            <div class="bg-black/40 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-blue-300">📺 Séries</span>
                    <button onclick="copyUrl('url-series')" class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded bg-gray-700/50 hover:bg-gray-600/50 transition">
                        📋 Copiar
                    </button>
                </div>
                <code id="url-series" class="text-green-400 text-xs sm:text-sm break-all select-all">{{ url('/api/playlist/series.m3u') }}?token=SEU_TOKEN</code>
            </div>

            {{-- Tudo --}}
            <div class="bg-black/40 rounded-lg p-4 border border-blue-500/30">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-yellow-300">⭐ TUDO (TV + Filmes + Séries + Animes + Novelas + Desenhos)</span>
                    <button onclick="copyUrl('url-all')" class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded bg-gray-700/50 hover:bg-gray-600/50 transition">
                        📋 Copiar
                    </button>
                </div>
                <code id="url-all" class="text-green-400 text-xs sm:text-sm break-all select-all">{{ url('/api/playlist/all.m3u') }}?token=SEU_TOKEN</code>
            </div>
        </div>
    </div>

    {{-- ─── PASSO 3: VLC Desktop ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-orange-600 text-white font-bold text-lg">3</span>
            <h2 class="text-xl font-semibold text-white">Configurar no VLC (Windows/Mac/Linux)</h2>
        </div>

        <div class="space-y-4">
            <div class="flex items-start space-x-4">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-700 text-center text-sm font-medium text-gray-300 leading-7">A</span>
                <div>
                    <p class="text-white font-medium">Baixe o VLC</p>
                    <p class="text-gray-400 text-sm">
                        Se ainda não tem, baixe em
                        <a href="https://www.videolan.org/vlc/" target="_blank" class="text-blue-400 hover:underline">videolan.org/vlc</a>
                        (gratuito).
                    </p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-700 text-center text-sm font-medium text-gray-300 leading-7">B</span>
                <div>
                    <p class="text-white font-medium">Abra o VLC e vá em Mídia</p>
                    <p class="text-gray-400 text-sm">No menu superior, clique em <strong class="text-white">Mídia</strong> → <strong class="text-white">Abrir Transmissão de Rede...</strong></p>
                    <p class="text-gray-500 text-xs mt-1">Atalho: <kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-xs text-gray-300">Ctrl+N</kbd></p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-700 text-center text-sm font-medium text-gray-300 leading-7">C</span>
                <div>
                    <p class="text-white font-medium">Cole a URL da playlist</p>
                    <p class="text-gray-400 text-sm">
                        No campo <strong class="text-white">"URL de rede"</strong>, cole a URL da playlist desejada (do Passo 2, com seu token real).
                    </p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-700 text-center text-sm font-medium text-gray-300 leading-7">D</span>
                <div>
                    <p class="text-white font-medium">Clique em "Reproduzir"</p>
                    <p class="text-gray-400 text-sm">
                        O VLC vai carregar a lista. Use <strong class="text-white">Exibir → Lista de Reprodução</strong>
                        (<kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-xs text-gray-300">Ctrl+L</kbd>)
                        para ver todos os canais/conteúdos organizados por grupo.
                    </p>
                </div>
            </div>

            {{-- Screenshot-like visual --}}
            <div class="bg-black/60 rounded-lg p-4 border border-gray-600 text-center">
                <div class="inline-block text-left bg-gray-800 rounded-lg p-4 border border-gray-600 max-w-md">
                    <div class="flex items-center space-x-2 mb-3 pb-2 border-b border-gray-700">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span class="text-xs text-gray-400 ml-2">VLC media player</span>
                    </div>
                    <p class="text-gray-400 text-xs mb-2">Mídia → Abrir Transmissão de Rede</p>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-400 whitespace-nowrap">URL:</span>
                        <div class="flex-1 bg-white rounded px-2 py-1">
                            <span class="text-black text-xs truncate block">{{ url('/api/playlist/tv.m3u') }}?token=bs_...</span>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <span class="text-xs bg-blue-600 text-white px-4 py-1 rounded">▶ Reproduzir</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── PASSO 4: VLC Mobile ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-green-600 text-white font-bold text-lg">4</span>
            <h2 class="text-xl font-semibold text-white">Configurar no Celular (VLC Mobile / IPTV Smarters)</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- VLC Mobile --}}
            <div class="bg-black/40 rounded-lg p-4 border border-gray-700 space-y-3">
                <h3 class="text-blue-300 font-medium">📱 VLC Mobile (Android/iOS)</h3>
                <ol class="text-gray-400 text-sm space-y-2 list-decimal list-inside">
                    <li>Abra o VLC Mobile</li>
                    <li>Toque em <strong class="text-white">"Streams"</strong> (aba de rede)</li>
                    <li>Cole a URL da playlist com seu token</li>
                    <li>Toque em <strong class="text-white">"Abrir transmissão"</strong></li>
                </ol>
            </div>

            {{-- IPTV Smarters --}}
            <div class="bg-black/40 rounded-lg p-4 border border-gray-700 space-y-3">
                <h3 class="text-blue-300 font-medium">📺 IPTV Smarters Pro</h3>
                <ol class="text-gray-400 text-sm space-y-2 list-decimal list-inside">
                    <li>Abra o IPTV Smarters</li>
                    <li>Selecione <strong class="text-white">"Load Your Playlist or File/URL"</strong></li>
                    <li>Escolha <strong class="text-white">"M3U URL"</strong></li>
                    <li>Em <strong class="text-white">Playlist Name</strong>: <span class="text-purple-300">BaseStream</span></li>
                    <li>Em <strong class="text-white">File/URL</strong>: Cole a URL <code class="text-green-400 text-xs">all.m3u?token=...</code></li>
                    <li>Toque em <strong class="text-white">"Add User"</strong></li>
                </ol>
            </div>
        </div>
    </div>

    {{-- ─── PASSO 5: Outros Players ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-pink-600 text-white font-bold text-lg">5</span>
            <h2 class="text-xl font-semibold text-white">Outros Players Compatíveis</h2>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['name' => 'Kodi', 'desc' => 'Adicionar como fonte PVR IPTV Simple Client', 'icon' => '🎮'],
                ['name' => 'OTT Navigator', 'desc' => 'Adicionar playlist M3U na configuração', 'icon' => '📡'],
                ['name' => 'TiviMate', 'desc' => 'Ideal para Android TV / Fire Stick', 'icon' => '🔥'],
                ['name' => 'GSE Smart IPTV', 'desc' => 'Suporte M3U no iOS e Android', 'icon' => '📲'],
                ['name' => 'Perfect Player', 'desc' => 'Player leve para Android TV', 'icon' => '▶️'],
                ['name' => 'mpv', 'desc' => 'mpv --playlist=URL (terminal)', 'icon' => '💻'],
            ] as $player)
            <div class="bg-black/30 rounded-lg p-3 border border-gray-700/50">
                <div class="flex items-center space-x-2">
                    <span class="text-lg">{{ $player['icon'] }}</span>
                    <span class="text-white text-sm font-medium">{{ $player['name'] }}</span>
                </div>
                <p class="text-gray-500 text-xs mt-1">{{ $player['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ─── FAQ / Dicas ─── --}}
    <div class="glass rounded-xl border border-purple-500/20 p-6 space-y-4">
        <h2 class="text-xl font-semibold text-white">💡 Dicas & Solução de Problemas</h2>

        <div class="space-y-3">
            <div class="bg-black/30 rounded-lg p-4 border border-gray-700/50" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center justify-between w-full text-left">
                    <span class="text-gray-200 text-sm font-medium">O VLC não carrega a playlist?</span>
                    <span class="text-gray-500 text-xs" x-text="open ? '▲' : '▼'"></span>
                </button>
                <div x-show="open" x-cloak class="mt-3 text-gray-400 text-sm space-y-1">
                    <p>• Verifique se o token está correto e ativo</p>
                    <p>• Tente criar um token novo na página <a href="{{ route('dashboard.tokens') }}" class="text-blue-400 hover:underline">Devices</a></p>
                    <p>• Confirme que a URL está completa (com <code class="text-purple-300">?token=bs_...</code>)</p>
                </div>
            </div>

            <div class="bg-black/30 rounded-lg p-4 border border-gray-700/50" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center justify-between w-full text-left">
                    <span class="text-gray-200 text-sm font-medium">Canal fica em buffering / não abre?</span>
                    <span class="text-gray-500 text-xs" x-text="open ? '▲' : '▼'"></span>
                </button>
                <div x-show="open" x-cloak class="mt-3 text-gray-400 text-sm space-y-1">
                    <p>• Nem todos os canais ficam disponíveis 100% do tempo — depende do servidor upstream</p>
                    <p>• Tente outro canal do mesmo grupo</p>
                    <p>• Canais marcados como "Servidor 1/2/3" são fonte alternativas</p>
                </div>
            </div>

            <div class="bg-black/30 rounded-lg p-4 border border-gray-700/50" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center justify-between w-full text-left">
                    <span class="text-gray-200 text-sm font-medium">Posso usar a URL direto no navegador?</span>
                    <span class="text-gray-500 text-xs" x-text="open ? '▲' : '▼'"></span>
                </button>
                <div x-show="open" x-cloak class="mt-3 text-gray-400 text-sm space-y-1">
                    <p>• Sim! Ao abrir a URL <code class="text-green-400">.m3u</code> no navegador, ele vai baixar o arquivo da playlist.</p>
                    <p>• Você pode salvar e importar no player de sua preferência.</p>
                </div>
            </div>

            <div class="bg-black/30 rounded-lg p-4 border border-gray-700/50" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center justify-between w-full text-left">
                    <span class="text-gray-200 text-sm font-medium">Preciso renovar o token?</span>
                    <span class="text-gray-500 text-xs" x-text="open ? '▲' : '▼'"></span>
                </button>
                <div x-show="open" x-cloak class="mt-3 text-gray-400 text-sm space-y-1">
                    <p>• Tokens sem data de expiração funcionam indefinidamente</p>
                    <p>• Se definiu uma data de validade ao criar, crie um novo quando expirar</p>
                    <p>• Gerencie seus tokens em <a href="{{ route('dashboard.tokens') }}" class="text-blue-400 hover:underline">Devices & Tokens</a></p>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Link rápido para criar token ─── --}}
    <div class="text-center pb-4">
        <a href="{{ route('dashboard.tokens') }}"
           class="inline-flex items-center space-x-2 px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-xl text-white font-medium transition-colors">
            <span>🔑</span>
            <span>Criar Token Agora</span>
        </a>
    </div>
</div>

@push('scripts')
<script>
function copyUrl(elementId) {
    const el = document.getElementById(elementId);
    const text = el.textContent || el.innerText;
    navigator.clipboard.writeText(text).then(() => {
        // Feedback visual rápido
        const btn = el.parentElement.querySelector('button');
        const original = btn.textContent;
        btn.textContent = '✅ Copiado!';
        btn.classList.add('text-green-400');
        setTimeout(() => {
            btn.textContent = original;
            btn.classList.remove('text-green-400');
        }, 2000);
    });
}
</script>
@endpush
@endsection
