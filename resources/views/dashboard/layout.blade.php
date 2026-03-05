<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'BaseStream' }} - Italo Antonio</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='15' fill='%23007BFF'/><text x='50' y='65' font-size='50' fill='white' text-anchor='middle'>📡</text></svg>">

    <!-- Meta SEO -->
    <meta property="og:title" content="BaseStream - IPTV Pessoal">
    <meta property="og:description" content="Streams dinâmicos como BrazucaPlay para sua TV">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://basestream.railway.app">
    <meta name="description" content="BaseStream - Proxy IPTV dinâmico multi-device com cache Redis e queue async">

    {{-- Tailwind CDN (50kb gzipped — Samsung Tizen friendly) --}}
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

    {{-- HTMX — dynamic partials sem page reload --}}
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>

    {{-- Alpine.js — modals, dropdowns, interações leves --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>


    <style>
        body { background: linear-gradient(135deg, #0f0a1a 0%, #1e1040 50%, #0f0a1a 100%); }
        .htmx-indicator { opacity: 0; transition: opacity 200ms ease-in; }
        .htmx-request .htmx-indicator, .htmx-request.htmx-indicator { opacity: 1; }
        [x-cloak] { display: none !important; }
        .glass { background: rgba(255,255,255,0.06); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .card-hover { transition: transform 0.2s, border-color 0.2s; }
        .card-hover:hover { transform: scale(1.02); border-color: rgba(168,85,247,0.4); }
    </style>

    @stack('head')
</head>
<body class="text-white min-h-screen antialiased">

    {{-- ─── Navbar ─── --}}
    <nav class="bg-black/50 backdrop-blur-md border-b border-purple-500/20 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Brand --}}
                <div class="flex items-center space-x-4">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                        <span class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">
                            Base<span class="text-blue-400">Stream</span>
                        </span>
                    </a>
                    @auth
                        <span class="hidden sm:inline text-xs px-3 py-1 rounded-full
                            {{ auth()->user()->isPro() ? 'bg-purple-500/30 text-purple-300' : 'bg-gray-700/50 text-gray-400' }}">
                            {{ strtoupper(auth()->user()->tier) }}
                        </span>
                    @endauth
                </div>

                {{-- Nav links --}}
                <div class="flex items-center space-x-1 sm:space-x-2">
                    @auth
                        <a href="{{ route('content.index') }}"
                           class="px-3 py-2 rounded-lg text-sm hover:bg-purple-500/20 transition-colors {{ request()->routeIs('content.*') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                            Conteúdo
                        </a>
                        <a href="{{ route('dashboard') }}"
                           class="px-3 py-2 rounded-lg text-sm hover:bg-purple-500/20 transition-colors {{ request()->routeIs('dashboard') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                            Overview
                        </a>
                        <a href="{{ route('dashboard.logs') }}"
                           class="px-3 py-2 rounded-lg text-sm hover:bg-purple-500/20 transition-colors {{ request()->routeIs('dashboard.logs') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                            Logs
                        </a>
                        <a href="{{ route('dashboard.tokens') }}"
                           class="px-3 py-2 rounded-lg text-sm hover:bg-purple-500/20 transition-colors {{ request()->routeIs('dashboard.tokens') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                            Devices
                        </a>
                        <a href="{{ route('dashboard.vlc') }}"
                           class="px-3 py-2 rounded-lg text-sm hover:bg-purple-500/20 transition-colors {{ request()->routeIs('dashboard.vlc') ? 'bg-purple-500/20 text-white' : 'text-gray-400' }}">
                            📡 VLC
                        </a>

                        <div class="hidden sm:block w-px h-6 bg-gray-700 mx-2"></div>

                        <span class="hidden sm:inline text-sm text-gray-400">{{ auth()->user()->name }}</span>

                        <form method="POST" action="{{ route('web.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-2 text-sm text-red-400 hover:text-red-300 transition-colors">
                                Sair
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- ─── Main Content ─── --}}
    <main id="content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-6 p-4 glass rounded-xl border border-green-500/30 text-green-300 text-sm"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 glass rounded-xl border border-red-500/30 text-red-300 text-sm"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 glass rounded-xl border border-red-500/30 text-red-300 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ─── Footer ─── --}}
    <footer class="border-t border-purple-500/10 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center text-gray-600 text-xs">
            BaseStream v1.0 by Italo Antonio &mdash; Uso pessoal. Streams públicos apenas.
        </div>
    </footer>

    <script>
        // Smooth scroll on HTMX swap
        document.body.addEventListener('htmx:afterSwap', (e) => {
            if (e.detail.target.id === 'content') window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>

    @stack('scripts')
</body>
</html>
