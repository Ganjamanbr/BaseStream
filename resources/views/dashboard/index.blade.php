@extends('dashboard.layout')

@section('content')
    {{-- ─── Stats Cards (HTMX auto-refresh 30s) ─── --}}
    <div hx-get="{{ route('dashboard.stats.partial') }}" hx-trigger="every 30s" hx-swap="innerHTML">
        @include('dashboard.partials.stats-cards')
    </div>

    {{-- ─── Quick Access Streams ─── --}}
    <div class="mt-10">
        <h2 class="text-lg font-semibold text-gray-300 mb-4">⚡ Acesso Rápido</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @php
                $quickStreams = [
                    ['id' => 'globo',       'name' => 'Globo',       'color' => 'from-blue-500 to-blue-700'],
                    ['id' => 'sportv',      'name' => 'SporTV',      'color' => 'from-green-500 to-green-700'],
                    ['id' => 'band',        'name' => 'Band',        'color' => 'from-yellow-500 to-orange-600'],
                    ['id' => 'sbt',         'name' => 'SBT',         'color' => 'from-purple-500 to-purple-700'],
                    ['id' => 'record',      'name' => 'Record',      'color' => 'from-red-500 to-red-700'],
                    ['id' => 'redebrasil',  'name' => 'Rede Brasil', 'color' => 'from-teal-500 to-teal-700'],
                ];
            @endphp

            @foreach ($quickStreams as $stream)
                <x-stream-card :stream="$stream" />
            @endforeach
        </div>
    </div>

    {{-- ─── Devices / Tokens ─── --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-300">📱 Seus Devices</h2>
            <button
                x-data @click="$dispatch('open-modal')"
                class="bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-400 hover:to-pink-400
                       text-white px-4 py-2 rounded-xl text-sm font-medium transition-all shadow-lg shadow-purple-500/20">
                + Novo Token
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($tokens as $token)
                <x-token-item :token="$token" />
            @empty
                <div class="col-span-full text-center py-12 glass rounded-2xl border border-purple-500/10">
                    <p class="text-gray-500 text-lg">Nenhum device configurado</p>
                    <p class="text-gray-600 text-sm mt-1">Crie um token para conectar sua Smart TV ou device.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ─── Recent Logs (HTMX auto-refresh 10s) ─── --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-300">📊 Últimos Streams</h2>
            <div class="flex items-center space-x-3">
                <span class="htmx-indicator text-purple-400 text-xs animate-pulse">⏳ atualizando...</span>
                <a href="{{ route('dashboard.logs') }}" class="text-purple-400 hover:text-purple-300 text-sm transition-colors">
                    Ver todos →
                </a>
            </div>
        </div>

        <div hx-get="{{ route('dashboard.logs.partial') }}" hx-trigger="every 10s" hx-swap="innerHTML" hx-indicator=".htmx-indicator">
            @include('dashboard.partials.logs-table')
        </div>
    </div>

    {{-- ─── Create Token Modal ─── --}}
    <div x-data="{ open: false }" @open-modal.window="open = true" x-cloak>
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
            <div class="glass border border-purple-500/30 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-purple-900/30"
                 @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-lg font-semibold text-white mb-4">Criar Token de Device</h3>
                <form method="POST" action="/api/tokens">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Nome do Device</label>
                            <input type="text" name="name" placeholder="Ex: Samsung TV Sala"
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
