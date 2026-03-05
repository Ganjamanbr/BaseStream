@extends('dashboard.layout')

@section('content')
    {{-- ─── Flash: Token Criado ─── --}}
    @if (session('success'))
        <div class="mb-6 glass rounded-2xl p-5 border border-green-500/30 bg-green-500/10" x-data="{ copied: false }">
            <div class="flex items-center space-x-3 mb-3">
                <span class="text-green-400 text-xl">✅</span>
                <h3 class="text-green-300 font-bold text-lg">Token Criado com Sucesso!</h3>
            </div>
            <p class="text-gray-300 text-sm mb-3">
                ⚠️ <strong>Copie seu token agora!</strong> Ele não será mostrado novamente.
            </p>
            <div class="flex items-center space-x-2">
                <code class="flex-1 bg-black/40 text-green-300 px-4 py-3 rounded-xl text-xs font-mono break-all select-all">{{ str_replace(['Token criado! Copie e salve agora: '], '', session('success')) }}</code>
                <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)"
                        class="bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 px-4 py-3 rounded-xl text-sm transition-colors whitespace-nowrap"
                        x-text="copied ? '✓ Copiado!' : '📋 Copiar'">
                </button>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 glass rounded-2xl p-4 border border-red-500/30 bg-red-500/10">
            <p class="text-red-300 text-sm">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- ─── Header ─── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Devices & Tokens</h1>
            <p class="text-gray-500 text-sm mt-1">
                {{ $tokens->where('is_active', true)->count() }} ativos de {{ $maxTokens }} permitidos
                <span class="text-purple-400">({{ strtoupper(auth()->user()->tier) }})</span>
            </p>
        </div>

        <button x-data @click="$dispatch('open-modal')"
                class="bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-400 hover:to-pink-400
                       text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all shadow-lg shadow-purple-500/20">
            + Novo Device
        </button>
    </div>

    {{-- ─── Token Cards Grid ─── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($tokens as $token)
            <div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500/30 to-pink-500/30
                                    flex items-center justify-center text-lg">
                            📱
                        </div>
                        <div>
                            <h3 class="font-medium text-white text-sm">{{ $token->name }}</h3>
                            <p class="text-gray-500 text-xs">Criado {{ $token->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <span class="w-2.5 h-2.5 rounded-full {{ $token->is_active ? 'bg-emerald-400 shadow-lg shadow-emerald-400/50' : 'bg-gray-600' }}"></span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Último uso</span>
                        <span class="text-gray-300">{{ $token->last_used_at?->diffForHumans() ?? 'Nunca' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">IP</span>
                        <span class="text-gray-300 font-mono text-xs">{{ $token->last_ip ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Streams</span>
                        <span class="text-purple-300 font-semibold">{{ $token->streams_count ?? 0 }}</span>
                    </div>
                </div>

                {{-- Token value (spoiler) --}}
                <div class="mt-4" x-data="{ show: false }">
                    <button @click="show = !show" class="text-xs text-gray-500 hover:text-gray-400 transition-colors">
                        <span x-show="!show">👁 Mostrar token</span>
                        <span x-show="show">🙈 Ocultar</span>
                    </button>
                    <div x-show="show" x-cloak class="mt-2 p-2 bg-black/30 rounded-lg border border-purple-500/20">
                        <code class="text-xs text-purple-300 break-all select-all">{{ $token->token }}</code>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-4 pt-3 border-t border-white/5 flex justify-end">
                    <form method="POST" action="{{ route('dashboard.tokens.revoke', $token->id) }}"
                          hx-delete="{{ route('dashboard.tokens.revoke', $token->id) }}"
                          hx-target="closest .glass"
                          hx-swap="outerHTML"
                          hx-confirm="Revogar token '{{ $token->name }}'? O device será desconectado.">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-red-400 hover:text-red-300 text-xs font-medium px-3 py-1.5 rounded-lg
                                       hover:bg-red-500/10 transition-colors">
                            Revogar
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 glass rounded-2xl border border-purple-500/10">
                <div class="text-5xl mb-4">📱</div>
                <p class="text-gray-400 text-lg">Nenhum device conectado</p>
                <p class="text-gray-600 text-sm mt-2">Crie um token para conectar sua Smart TV, celular ou outro device.</p>
                <button x-data @click="$dispatch('open-modal')"
                        class="mt-6 bg-gradient-to-r from-purple-500 to-pink-500 text-white px-6 py-2.5 rounded-xl text-sm font-medium">
                    Criar primeiro token
                </button>
            </div>
        @endforelse
    </div>

    {{-- ─── Create Token Modal ─── --}}
    <div x-data="{ open: false }" @open-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
            <div class="glass border border-purple-500/30 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-purple-900/30"
                 @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-lg font-semibold text-white mb-4">Adicionar Device</h3>
                <form method="POST" action="{{ route('dashboard.tokens.create') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Nome do Device</label>
                            <input type="text" name="name" placeholder="Ex: Samsung TV Sala, VLC PC, etc"
                                   class="w-full bg-white/5 border border-purple-500/30 rounded-xl px-4 py-3 text-white
                                          placeholder-gray-500 focus:border-purple-400 focus:ring-1 focus:ring-purple-400/50
                                          focus:outline-none transition-colors"
                                   required>
                        </div>
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-400 hover:to-pink-400
                                       text-white py-3 rounded-xl font-medium transition-all shadow-lg shadow-purple-500/25">
                            Gerar Token
                        </button>
                    </div>
                </form>
                <button @click="open = false" class="mt-3 w-full text-gray-500 hover:text-gray-400 text-sm py-2 transition-colors">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
@endsection
