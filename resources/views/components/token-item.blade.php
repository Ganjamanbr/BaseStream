@props(['token'])

<div class="glass rounded-2xl p-5 border border-purple-500/10 card-hover">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/30 to-pink-500/30
                        flex items-center justify-center text-sm">
                📱
            </div>
            <h3 class="font-medium text-white text-sm">{{ $token->name }}</h3>
        </div>
        <span class="w-2 h-2 rounded-full {{ $token->is_active ? 'bg-emerald-400 shadow-lg shadow-emerald-400/50' : 'bg-gray-600' }}"></span>
    </div>
    <div class="text-sm text-gray-400 space-y-1.5">
        <div class="flex justify-between">
            <span class="text-gray-500">Último uso</span>
            <span>{{ $token->last_used_at?->diffForHumans() ?? 'Nunca' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500">IP</span>
            <span class="font-mono text-xs">{{ $token->last_ip ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500">Streams</span>
            <span class="text-purple-300 font-semibold">{{ $token->streams_count ?? 0 }}</span>
        </div>
    </div>
    <div class="mt-4 pt-3 border-t border-white/5 flex justify-end">
        <form method="POST" action="/api/tokens/{{ $token->id }}"
              hx-delete="/api/tokens/{{ $token->id }}"
              hx-target="closest .glass"
              hx-swap="outerHTML"
              hx-confirm="Revogar token '{{ $token->name }}'?">
            @csrf @method('DELETE')
            <button type="submit" class="text-red-400 hover:text-red-300 text-xs font-medium px-2 py-1 rounded hover:bg-red-500/10 transition-colors">
                Revogar
            </button>
        </form>
    </div>
</div>
