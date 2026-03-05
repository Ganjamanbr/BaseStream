<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Conteúdo' }} - BaseStream</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='15' fill='%23007BFF'/><text x='50' y='65' font-size='50' fill='white' text-anchor='middle'>📡</text></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'bs-dark':   '#0f0a1a',
                        'bs-card':   'rgba(255,255,255,0.06)',
                        'bs-accent': '#a855f7',
                        'bs-pink':   '#ec4899',
                        'bs-green':  '#34d399',
                    }
                }
            }
        }
    </script>

    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { background: linear-gradient(135deg, #0f0a1a 0%, #1e1040 50%, #0f0a1a 100%); }
        .htmx-indicator { opacity: 0; transition: opacity 200ms ease-in; }
        .htmx-request .htmx-indicator, .htmx-request.htmx-indicator { opacity: 1; }
        [x-cloak] { display: none !important; }
        .glass { background: rgba(255,255,255,0.06); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover { transform: scale(1.03); box-shadow: 0 0 20px rgba(168,85,247,0.3); }
        .thumb-fallback { background: linear-gradient(135deg, #1e1040 0%, #2d1b69 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>

    @stack('head')
</head>
<body class="text-white min-h-screen antialiased">

    {{-- ─── Navbar ─── --}}
    <nav class="bg-black/60 backdrop-blur-xl border-b border-purple-500/20 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                {{-- Brand --}}
                <a href="{{ route('content.index') }}" class="flex items-center space-x-2">
                    <span class="text-xl font-bold bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">
                        Base<span class="text-blue-400">Stream</span>
                    </span>
                </a>

                {{-- Nav links --}}
                <div class="flex items-center space-x-1 overflow-x-auto scrollbar-hide">
                    <a href="{{ route('content.index') }}"
                       class="px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.index') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Início
                    </a>
                    <a href="{{ route('content.tv') }}"
                       class="px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.tv') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        TV ao Vivo
                    </a>
                    <a href="{{ route('content.filmes') }}"
                       class="px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.filmes*') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Filmes
                    </a>
                    <a href="{{ route('content.series') }}"
                       class="px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.series') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Séries
                    </a>
                    <a href="{{ route('content.animes') }}"
                       class="px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.animes') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Animes
                    </a>
                    <a href="{{ route('content.novelas') }}"
                       class="hidden sm:inline-block px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.novelas') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Novelas
                    </a>
                    <a href="{{ route('content.desenhos') }}"
                       class="hidden sm:inline-block px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.desenhos') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Desenhos
                    </a>
                    <a href="{{ route('content.doramas') }}"
                       class="hidden md:inline-block px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.doramas') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Doramas
                    </a>
                    <a href="{{ route('content.pluto') }}"
                       class="hidden md:inline-block px-3 py-2 rounded-lg text-sm whitespace-nowrap hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.pluto') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                        Pluto TV
                    </a>
                </div>

                {{-- Search + User --}}
                <div class="flex items-center space-x-2">
                    <form action="{{ route('content.search') }}" method="GET" class="hidden sm:flex items-center">
                        <input type="text" name="q" placeholder="Buscar..."
                               value="{{ request('q') }}"
                               class="bg-white/10 border border-purple-500/30 rounded-lg px-3 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-400 w-40 lg:w-56">
                    </form>

                    <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-white text-sm px-2" title="Dashboard">
                        ⚙️
                    </a>

                    <form method="POST" action="{{ route('web.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-400 hover:text-red-300 text-sm px-2" title="Sair">
                            ✕
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    {{-- ─── Main ─── --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if (session('error'))
            <div class="mb-4 p-3 glass rounded-xl border border-red-500/30 text-red-300 text-sm"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ─── Footer ─── --}}
    <footer class="border-t border-purple-500/10 py-4 mt-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-gray-600 text-xs">
            BaseStream v1.0 by Italo Antonio &mdash; Uso pessoal
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
