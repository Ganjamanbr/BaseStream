<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BaseStream</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='15' fill='%23007BFF'/><text x='50' y='65' font-size='50' fill='white' text-anchor='middle'>📡</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bs-dark': '#0f0a1a',
                        'bs-accent': '#a855f7',
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
    <style>
        body { background: linear-gradient(135deg, #0f0a1a 0%, #1e1040 50%, #0f0a1a 100%); }
    </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">
                Base<span class="text-blue-400">Stream</span>
            </h1>
            <p class="text-gray-500 text-sm mt-2">Painel de gerenciamento IPTV — by Italo Antonio</p>
        </div>

        {{-- Card --}}
        <div class="bg-white/5 backdrop-blur-xl border border-purple-500/20 rounded-2xl p-8 shadow-2xl shadow-purple-900/20">
            <h2 class="text-xl font-semibold text-white mb-6">Entrar na conta</h2>

            {{-- Error area (filled by HTMX or blade) --}}
            @if ($errors->any())
                <div id="login-errors" class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('web.login.submit') }}">
                @csrf

                {{-- Email --}}
                <div class="mb-5">
                    <label for="email" class="block text-sm text-gray-400 mb-2">E-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-3 rounded-xl bg-white/5 border border-purple-500/30 text-white
                                  placeholder-gray-500 focus:outline-none focus:border-purple-400 focus:ring-1
                                  focus:ring-purple-400/50 transition-colors"
                           placeholder="seu@email.com">
                </div>

                {{-- Password --}}
                <div class="mb-6">
                    <label for="password" class="block text-sm text-gray-400 mb-2">Senha</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 rounded-xl bg-white/5 border border-purple-500/30 text-white
                                  placeholder-gray-500 focus:outline-none focus:border-purple-400 focus:ring-1
                                  focus:ring-purple-400/50 transition-colors"
                           placeholder="••••••••">
                </div>

                {{-- Remember --}}
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center space-x-2 text-sm text-gray-400 cursor-pointer">
                        <input type="checkbox" name="remember"
                               class="w-4 h-4 rounded bg-white/10 border-purple-500/30 text-purple-500 focus:ring-purple-500/50">
                        <span>Lembrar acesso</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full py-3 px-4 rounded-xl font-semibold text-white
                               bg-gradient-to-r from-purple-500 to-pink-500
                               hover:from-purple-400 hover:to-pink-400
                               transform hover:scale-[1.02] transition-all duration-200
                               shadow-lg shadow-purple-500/25">
                    Entrar
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-500 text-xs">
                    Acesso restrito — Uso pessoal
                </p>
            </div>
        </div>
    </div>

</body>
</html>
