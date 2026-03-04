<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BaseStream') - Dashboard</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'bs-dark': '#141414',
                        'bs-card': '#1f1f1f',
                        'bs-accent': '#e50914',
                        'bs-green': '#46d369',
                    }
                }
            }
        }
    </script>

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { background-color: #141414; }
        .htmx-indicator { opacity: 0; transition: opacity 200ms ease-in; }
        .htmx-request .htmx-indicator { opacity: 1; }
        .htmx-request.htmx-indicator { opacity: 1; }
    </style>
</head>
<body class="bg-bs-dark text-white min-h-screen">
    <!-- Navbar -->
    <nav class="bg-black/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-bs-accent font-bold text-2xl tracking-tight">
                        BaseStream
                    </a>
                    <span class="text-gray-500 text-sm">Proxy Dashboard</span>
                </div>
                <div class="flex items-center space-x-4">
                    @auth
                        <span class="text-gray-400 text-sm">{{ auth()->user()->name }}</span>
                        <span class="px-2 py-1 text-xs rounded-full {{ auth()->user()->isPro() ? 'bg-bs-accent' : 'bg-gray-700' }}">
                            {{ strtoupper(auth()->user()->tier) }}
                        </span>
                        <form method="POST" action="/logout" class="inline">
                            @csrf
                            <button type="submit" class="text-gray-400 hover:text-white text-sm">Sair</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    <!-- Toast notifications -->
    <div id="toast" class="fixed bottom-4 right-4 z-50"></div>
</body>
</html>
