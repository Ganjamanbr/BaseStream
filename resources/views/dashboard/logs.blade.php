@extends('dashboard.layout')

@section('content')
    {{-- ─── Header with filters ─── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Stream Logs</h1>
            <p class="text-gray-500 text-sm mt-1">Histórico de resoluções de stream</p>
        </div>

        {{-- Time-range filter buttons (HTMX) --}}
        <div class="flex items-center space-x-2" x-data="{ active: '{{ request('range', 'today') }}' }">
            @foreach (['1h' => 'Última hora', 'today' => 'Hoje', '7d' => '7 dias', 'all' => 'Tudo'] as $range => $label)
                <button
                    hx-get="{{ route('dashboard.logs') }}?range={{ $range }}"
                    hx-target="#logs-content"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    @click="active = '{{ $range }}'"
                    :class="active === '{{ $range }}'
                        ? 'bg-purple-500/30 text-purple-300 border-purple-500/50'
                        : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'"
                    class="px-3 py-1.5 text-xs rounded-lg border transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ─── Logs stats mini-bar ─── --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="glass rounded-xl p-4 border border-purple-500/10">
            <p class="text-gray-500 text-xs uppercase tracking-wider">Total</p>
            <p class="text-xl font-bold text-white">{{ $logs->count() }}</p>
        </div>
        <div class="glass rounded-xl p-4 border border-purple-500/10">
            <p class="text-gray-500 text-xs uppercase tracking-wider">Sucesso</p>
            <p class="text-xl font-bold text-emerald-400">{{ $logs->where('status', 'success')->count() }}</p>
        </div>
        <div class="glass rounded-xl p-4 border border-purple-500/10">
            <p class="text-gray-500 text-xs uppercase tracking-wider">Tempo Médio</p>
            <p class="text-xl font-bold text-purple-400">{{ (int) $logs->avg('response_time_ms') }}ms</p>
        </div>
    </div>

    {{-- ─── Logs Table ─── --}}
    <div id="logs-content">
        @include('dashboard.partials.logs-table')
    </div>
@endsection
