@extends('dashboard.layout')

@push('head')
<title>IPTV TV Apps - BaseStream</title>
@endpush

@section('content')

    {{-- ─── Flash: Geração de credenciais ─── --}}
    @if (session('xtream_generated'))
        @php $gen = session('xtream_generated'); @endphp
        <div class="mb-6 glass rounded-2xl p-5 border border-green-500/30 bg-green-500/10"
             x-data="{ copied_u: false, copied_p: false }">
            <div class="flex items-center space-x-3 mb-4">
                <span class="text-green-400 text-2xl">✅</span>
                <h3 class="text-green-300 font-bold text-lg">Credenciais geradas! Copie agora.</h3>
            </div>
            <p class="text-amber-400 text-sm mb-4">
                ⚠️ A senha NÃO será exibida novamente. Salve-a em local seguro antes de sair desta página.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {{-- Usuário --}}
                <div>
                    <p class="text-gray-400 text-xs mb-1">Usuário</p>
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 bg-black/40 text-green-300 px-4 py-3 rounded-xl text-sm font-mono select-all">{{ $gen['username'] }}</code>
                        <button @click="navigator.clipboard.writeText('{{ $gen['username'] }}'); copied_u = true; setTimeout(() => copied_u = false, 2000)"
                                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-3 rounded-xl text-sm transition-colors whitespace-nowrap"
                                x-text="copied_u ? '✓' : '📋'">
                        </button>
                    </div>
                </div>
                {{-- Senha --}}
                <div>
                    <p class="text-gray-400 text-xs mb-1">Senha</p>
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 bg-black/40 text-green-300 px-4 py-3 rounded-xl text-sm font-mono select-all">{{ $gen['password'] }}</code>
                        <button @click="navigator.clipboard.writeText('{{ $gen['password'] }}'); copied_p = true; setTimeout(() => copied_p = false, 2000)"
                                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-3 rounded-xl text-sm transition-colors whitespace-nowrap"
                                x-text="copied_p ? '✓' : '📋'">
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="mb-6 glass rounded-2xl p-4 border border-green-500/30 bg-green-500/10">
            <p class="text-green-300 text-sm">✅ {{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 glass rounded-2xl p-4 border border-red-500/30 bg-red-500/10">
            <p class="text-red-300 text-sm">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- ─── Cabeçalho ─── --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-white">📺 IPTV TV Apps</h1>
        <p class="text-gray-500 text-sm mt-1">
            Configure apps como TiviMate, IPTV Smarters, GSE Player e OTT Navigator na sua Smart TV ou Android TV.
        </p>
    </div>

    {{-- ─── Credenciais Xtream Codes ─── --}}
    <div class="glass rounded-2xl p-6 border border-purple-500/20 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-white font-semibold text-lg flex items-center space-x-2">
                    <span>🔑</span>
                    <span>Credenciais Xtream Codes</span>
                </h2>
                <p class="text-gray-500 text-xs mt-1">
                    Protocolo padrão usado pela maioria dos apps de IPTV profissionais.
                </p>
            </div>
            @if ($user->hasXtreamCredentials())
                <span class="text-xs px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    ● Ativo
                </span>
            @else
                <span class="text-xs px-3 py-1 rounded-full bg-gray-700/50 text-gray-400">
                    Inativo
                </span>
            @endif
        </div>

        @if ($user->hasXtreamCredentials())
            {{-- Credentials display --}}
            <div class="space-y-4" x-data="{ showUser: false }">
                {{-- Server URL --}}
                <div x-data="{ copied: false }">
                    <label class="block text-xs text-gray-500 mb-1.5">Servidor (Server URL)</label>
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 bg-black/40 text-cyan-300 px-4 py-2.5 rounded-xl text-sm font-mono select-all break-all">{{ url('/') }}</code>
                        <button @click="navigator.clipboard.writeText('{{ url('/') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                x-text="copied ? '✓ Copiado' : '📋'"
                                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-2.5 rounded-xl text-sm transition-colors whitespace-nowrap">
                        </button>
                    </div>
                </div>

                {{-- Username --}}
                <div x-data="{ copied: false }">
                    <label class="block text-xs text-gray-500 mb-1.5">Usuário (Username)</label>
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 bg-black/40 text-purple-300 px-4 py-2.5 rounded-xl text-sm font-mono select-all">{{ $user->xtream_username }}</code>
                        <button @click="navigator.clipboard.writeText('{{ $user->xtream_username }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                x-text="copied ? '✓ Copiado' : '📋'"
                                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-2.5 rounded-xl text-sm transition-colors whitespace-nowrap">
                        </button>
                    </div>
                </div>

                {{-- Password (hidden) --}}
                <div x-data="{ show: false }">
                    <label class="block text-xs text-gray-500 mb-1.5">Senha</label>
                    <div class="flex items-center space-x-2">
                        <div class="flex-1 bg-black/40 text-pink-300 px-4 py-2.5 rounded-xl text-sm font-mono">
                            <span x-show="!show" class="text-gray-600">••••••••••••••••</span>
                            <span x-show="show" x-cloak class="text-pink-300 text-xs">
                                (senha não exibida — crie novas credenciais se perdeu a senha)
                            </span>
                        </div>
                        <button @click="show = !show"
                                x-text="show ? '🙈' : '👁'"
                                class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-2.5 rounded-xl text-sm transition-colors">
                        </button>
                    </div>
                </div>

                {{-- Port info --}}
                <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <p class="text-blue-300 text-xs">
                        💡 <strong>Porta:</strong> HTTPS (443) — deixe o campo porta em branco ou use 443 no app.
                    </p>
                </div>

                {{-- Revoke button --}}
                <div class="pt-2">
                    <form method="POST" action="{{ route('dashboard.iptv.revoke') }}"
                          onsubmit="return confirm('Revogar credenciais IPTV? Todos os apps configurados perderão acesso.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-red-400 hover:text-red-300 text-xs font-medium px-4 py-2 rounded-xl hover:bg-red-500/10 border border-red-500/20 transition-colors">
                            🗑 Revogar credenciais
                        </button>
                    </form>
                </div>
            </div>

        @else
            {{-- Generate button --}}
            <div class="text-center py-6">
                <div class="text-5xl mb-4">📡</div>
                <p class="text-gray-400 text-sm mb-6">
                    Nenhuma credencial gerada ainda.<br>
                    Gere suas credenciais para usar nos apps de TV.
                </p>
                <form method="POST" action="{{ route('dashboard.iptv.generate') }}">
                    @csrf
                    <button type="submit"
                            class="bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-400 hover:to-pink-400
                                   text-white px-8 py-3 rounded-xl font-medium transition-all shadow-lg shadow-purple-500/25">
                        🔑 Gerar Credenciais IPTV
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- ─── URL M3U Alternativa ─── --}}
    @if ($user->hasXtreamCredentials())
    <div class="glass rounded-2xl p-6 border border-purple-500/20 mb-6">
        <h2 class="text-white font-semibold mb-4 flex items-center space-x-2">
            <span>📄</span>
            <span>URL M3U (alternativa)</span>
        </h2>
        <p class="text-gray-500 text-xs mb-4">
            Para apps mais simples que aceitam apenas URL M3U (VLC, Kodi, etc).
        </p>

        @php
            $m3uUrl = url('/get.php') . '?' . http_build_query([
                'username' => $user->xtream_username,
                'password' => '__SENHA__',
                'type'     => 'm3u_plus',
                'output'   => 'ts',
            ]);
        @endphp

        <div x-data="{ copied: false }">
            <div class="flex items-center space-x-2">
                <code class="flex-1 bg-black/40 text-yellow-300 px-4 py-2.5 rounded-xl text-xs font-mono select-all break-all">
                    {{ url('/get.php') }}?username={{ $user->xtream_username }}&password=<span class="text-red-400">SUA_SENHA</span>&type=m3u_plus&output=ts
                </code>
                <button @click="navigator.clipboard.writeText('{{ url('/get.php') }}?username={{ $user->xtream_username }}&password=SUA_SENHA&type=m3u_plus&output=ts'); copied = true; setTimeout(() => copied = false, 2000)"
                        x-text="copied ? '✓' : '📋'"
                        class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-3 py-2.5 rounded-xl text-sm transition-colors whitespace-nowrap">
                </button>
            </div>
        </div>
        <p class="text-red-400 text-xs mt-2">⚠️ Substitua <code>SUA_SENHA</code> pela senha gerada.</p>
    </div>
    @endif

    {{-- ─── Guia de configuração por app ─── --}}
    <div class="glass rounded-2xl p-6 border border-purple-500/20">
        <h2 class="text-white font-semibold mb-6 flex items-center space-x-2">
            <span>📱</span>
            <span>Como configurar nos apps</span>
        </h2>

        <div class="space-y-4" x-data="{ open: null }">

            {{-- TiviMate --}}
            <div class="border border-purple-500/15 rounded-xl overflow-hidden">
                <button @click="open = open === 'tivimate' ? null : 'tivimate'"
                        class="w-full flex items-center justify-between p-4 text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/30 to-cyan-500/30 flex items-center justify-center text-lg">📺</div>
                        <div>
                            <p class="text-white font-medium text-sm">TiviMate</p>
                            <p class="text-gray-500 text-xs">Android TV — O melhor app IPTV para TV</p>
                        </div>
                    </div>
                    <span class="text-gray-400" x-text="open === 'tivimate' ? '▲' : '▼'"></span>
                </button>
                <div x-show="open === 'tivimate'" x-cloak class="px-4 pb-4 border-t border-white/5 pt-4">
                    <ol class="space-y-2 text-sm text-gray-300 list-decimal list-inside">
                        <li>Abra <strong>TiviMate</strong> e vá em <span class="text-purple-300">Configurações → Adicionar Playlist</span></li>
                        <li>Selecione <strong>Xtream Codes</strong></li>
                        <li>Servidor: <code class="text-cyan-300 bg-black/30 px-2 py-0.5 rounded">{{ url('/') }}</code></li>
                        <li>Usuário: <code class="text-purple-300 bg-black/30 px-2 py-0.5 rounded">{{ $user->xtream_username ?? 'seu_usuario' }}</code></li>
                        <li>Senha: <span class="text-pink-300">(a senha gerada acima)</span></li>
                        <li>Toque em <strong>Adicionar</strong> e aguarde o carregamento</li>
                    </ol>
                </div>
            </div>

            {{-- IPTV Smarters Pro --}}
            <div class="border border-purple-500/15 rounded-xl overflow-hidden">
                <button @click="open = open === 'smarters' ? null : 'smarters'"
                        class="w-full flex items-center justify-between p-4 text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500/30 to-red-500/30 flex items-center justify-center text-lg">🎬</div>
                        <div>
                            <p class="text-white font-medium text-sm">IPTV Smarters Pro</p>
                            <p class="text-gray-500 text-xs">Android TV, iOS, Android, Windows</p>
                        </div>
                    </div>
                    <span class="text-gray-400" x-text="open === 'smarters' ? '▲' : '▼'"></span>
                </button>
                <div x-show="open === 'smarters'" x-cloak class="px-4 pb-4 border-t border-white/5 pt-4">
                    <ol class="space-y-2 text-sm text-gray-300 list-decimal list-inside">
                        <li>Abra o app e toque em <strong>Adicionar novo usuário</strong></li>
                        <li>Selecione <strong>Xtream Codes API</strong></li>
                        <li>Nome do perfil: <span class="text-gray-400">BaseStream</span></li>
                        <li>URL do servidor: <code class="text-cyan-300 bg-black/30 px-2 py-0.5 rounded">{{ url('/') }}</code></li>
                        <li>Usuário e Senha: conforme gerado acima</li>
                        <li>Toque em <strong>Entrar</strong></li>
                    </ol>
                </div>
            </div>

            {{-- GSE Smart IPTV --}}
            <div class="border border-purple-500/15 rounded-xl overflow-hidden">
                <button @click="open = open === 'gse' ? null : 'gse'"
                        class="w-full flex items-center justify-between p-4 text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500/30 to-emerald-500/30 flex items-center justify-center text-lg">📡</div>
                        <div>
                            <p class="text-white font-medium text-sm">GSE Smart IPTV</p>
                            <p class="text-gray-500 text-xs">iOS, Android, Apple TV</p>
                        </div>
                    </div>
                    <span class="text-gray-400" x-text="open === 'gse' ? '▲' : '▼'"></span>
                </button>
                <div x-show="open === 'gse'" x-cloak class="px-4 pb-4 border-t border-white/5 pt-4">
                    <ol class="space-y-2 text-sm text-gray-300 list-decimal list-inside">
                        <li>Vá em <strong>Remote Playlists → +</strong></li>
                        <li>Selecione <strong>Xtream Codes API</strong></li>
                        <li>Preencha servidor, usuário e senha conforme acima</li>
                        <li>Toque em <strong>Save</strong></li>
                    </ol>
                </div>
            </div>

            {{-- OTT Navigator --}}
            <div class="border border-purple-500/15 rounded-xl overflow-hidden">
                <button @click="open = open === 'ott' ? null : 'ott'"
                        class="w-full flex items-center justify-between p-4 text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500/30 to-purple-500/30 flex items-center justify-center text-lg">🧭</div>
                        <div>
                            <p class="text-white font-medium text-sm">OTT Navigator</p>
                            <p class="text-gray-500 text-xs">Android TV</p>
                        </div>
                    </div>
                    <span class="text-gray-400" x-text="open === 'ott' ? '▲' : '▼'"></span>
                </button>
                <div x-show="open === 'ott'" x-cloak class="px-4 pb-4 border-t border-white/5 pt-4">
                    <ol class="space-y-2 text-sm text-gray-300 list-decimal list-inside">
                        <li>Toque em <strong>+ Adicionar Playlist</strong></li>
                        <li>Tipo: <strong>Xtream Codes</strong></li>
                        <li>Preencha URL do servidor, usuário e senha</li>
                        <li>Salve e aguarde o carregamento da lista</li>
                    </ol>
                </div>
            </div>

            {{-- VLC / M3U --}}
            <div class="border border-purple-500/15 rounded-xl overflow-hidden">
                <button @click="open = open === 'vlc' ? null : 'vlc'"
                        class="w-full flex items-center justify-between p-4 text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400/30 to-yellow-500/30 flex items-center justify-center text-lg">🔶</div>
                        <div>
                            <p class="text-white font-medium text-sm">VLC / M3U genérico</p>
                            <p class="text-gray-500 text-xs">VLC, Kodi, Jellyfin, qualquer player M3U</p>
                        </div>
                    </div>
                    <span class="text-gray-400" x-text="open === 'vlc' ? '▲' : '▼'"></span>
                </button>
                <div x-show="open === 'vlc'" x-cloak class="px-4 pb-4 border-t border-white/5 pt-4">
                    <p class="text-sm text-gray-300 mb-3">Use a URL M3U exibida na seção acima como playlist remota.</p>
                    <ol class="space-y-2 text-sm text-gray-300 list-decimal list-inside">
                        <li>No VLC: <strong>Mídia → Abrir fluxo de rede</strong></li>
                        <li>Cole a URL M3U completa (com usuário e senha preenchidos)</li>
                        <li>Toque em <strong>Reproduzir</strong></li>
                    </ol>
                </div>
            </div>

        </div>
    </div>

    {{-- ─── Conteúdo disponível ─── --}}
    <div class="mt-6 grid grid-cols-2 md:grid-cols-5 gap-3">
        @php
            $contentTypes = [
                ['icon' => '📡', 'label' => 'TV Ao Vivo',  'desc' => '8 categorias'],
                ['icon' => '🎬', 'label' => 'Filmes',       'desc' => 'Lançamentos + gêneros'],
                ['icon' => '📺', 'label' => 'Séries',       'desc' => 'Catálogo completo'],
                ['icon' => '🎌', 'label' => 'Animes',       'desc' => 'Catálogo completo'],
                ['icon' => '🌐', 'label' => 'Doramas',      'desc' => '+Novelas +Desenhos'],
            ];
        @endphp
        @foreach ($contentTypes as $ct)
            <div class="glass rounded-xl p-4 border border-purple-500/10 text-center">
                <div class="text-2xl mb-2">{{ $ct['icon'] }}</div>
                <p class="text-white text-xs font-medium">{{ $ct['label'] }}</p>
                <p class="text-gray-500 text-xs mt-0.5">{{ $ct['desc'] }}</p>
            </div>
        @endforeach
    </div>

@endsection
