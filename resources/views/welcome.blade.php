<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BaseStream - Netflix Pessoal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: {
            'bs-dark': '#141414', 'bs-accent': '#e50914', 'bs-green': '#46d369'
        }}}}
    </script>
</head>
<body class="bg-bs-dark text-white min-h-screen flex flex-col items-center justify-center">
    <div class="text-center max-w-2xl px-4">
        <!-- Logo -->
        <h1 class="text-6xl font-bold text-bs-accent mb-4 tracking-tight">BaseStream</h1>
        <p class="text-xl text-gray-300 mb-2">Sua Netflix Pessoal</p>
        <p class="text-gray-500 mb-8">Proxy IPTV inteligente. TV BR, filmes, séries e animes — direto na sua Samsung TV.</p>

        <!-- CTA -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-12">
            <a href="/dashboard" class="bg-bs-accent hover:bg-red-700 text-white px-8 py-3 rounded-lg font-semibold text-lg transition">
                Dashboard
            </a>
            <a href="/api/streams" class="bg-gray-800 hover:bg-gray-700 text-white px-8 py-3 rounded-lg font-semibold text-lg transition border border-gray-700">
                API Explorer
            </a>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-left">
            <div class="bg-gray-900/50 rounded-xl p-5 border border-gray-800">
                <div class="text-2xl mb-2">📡</div>
                <h3 class="font-semibold text-white mb-1">Stream Proxy</h3>
                <p class="text-sm text-gray-400">Resolve HLS dinâmicos com cache Redis e bypass CORS automático.</p>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-5 border border-gray-800">
                <div class="text-2xl mb-2">📱</div>
                <h3 class="font-semibold text-white mb-1">Multi-Device</h3>
                <p class="text-sm text-gray-400">Tokens nomeados: "Samsung TV", "PC", "Celular". Controle por device.</p>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-5 border border-gray-800">
                <div class="text-2xl mb-2">📊</div>
                <h3 class="font-semibold text-white mb-1">Dashboard</h3>
                <p class="text-sm text-gray-400">Logs em tempo real, métricas de sucesso/falha, uptime monitoring.</p>
            </div>
        </div>

        <!-- API Example -->
        <div class="mt-12 bg-gray-900 rounded-xl p-6 border border-gray-800 text-left">
            <p class="text-sm text-gray-400 mb-2">Exemplo de uso:</p>
            <code class="text-bs-green text-sm">
                GET /api/stream?id=tv-cultura&quality=HD&token=bs_seu_token
            </code>
        </div>

        <!-- Footer -->
        <p class="text-gray-600 text-xs mt-12">
            BaseStream v1.0.0 — Uso pessoal. Streams públicos apenas.
        </p>
    </div>
</body>
</html>
