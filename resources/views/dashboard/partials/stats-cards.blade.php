{{-- Stats Cards Partial (HTMX refreshable) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
        <div class="flex items-center justify-between">
            <p class="text-gray-400 text-sm">Streams Hoje</p>
            <span class="text-purple-400 text-lg">📺</span>
        </div>
        <p class="text-3xl font-bold text-white mt-2">{{ $stats['streams_today'] ?? 0 }}</p>
    </div>

    <div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
        <div class="flex items-center justify-between">
            <p class="text-gray-400 text-sm">Tokens Ativos</p>
            <span class="text-purple-400 text-lg">📱</span>
        </div>
        <p class="text-3xl font-bold text-white mt-2">
            {{ $stats['active_tokens'] ?? 0 }}
            <span class="text-sm text-gray-500 font-normal">/ {{ $stats['max_tokens'] ?? 2 }}</span>
        </p>
    </div>

    <div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
        <div class="flex items-center justify-between">
            <p class="text-gray-400 text-sm">Taxa de Sucesso</p>
            <span class="text-lg">{{ ($stats['success_rate'] ?? 100) >= 90 ? '✅' : '⚠️' }}</span>
        </div>
        <p class="text-3xl font-bold mt-2 {{ ($stats['success_rate'] ?? 100) >= 90 ? 'text-emerald-400' : 'text-yellow-400' }}">
            {{ $stats['success_rate'] ?? 100 }}%
        </p>
    </div>

    <div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
        <div class="flex items-center justify-between">
            <p class="text-gray-400 text-sm">Total Streams</p>
            <span class="text-purple-400 text-lg">📊</span>
        </div>
        <p class="text-3xl font-bold text-white mt-2">{{ $stats['total_streams'] ?? 0 }}</p>
    </div>
</div>
