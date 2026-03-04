{{-- Logs Table Partial (HTMX refreshable) --}}
<div class="glass rounded-2xl border border-purple-500/10 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-white/5">
                <tr>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider">Stream</th>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider">Device</th>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider hidden sm:table-cell">Qualidade</th>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider">Status</th>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider hidden md:table-cell">Tempo</th>
                    <th class="text-left px-4 py-3 text-gray-400 font-medium text-xs uppercase tracking-wider">Quando</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($logs ?? $recentLogs ?? [] as $log)
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-4 py-3">
                            <span class="text-white font-medium">{{ $log->stream_id }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-400">
                            {{ $log->apiToken?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="px-2 py-0.5 text-xs rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                {{ $log->quality }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($log->status === 'success')
                                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    OK
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-300 border border-red-500/30">
                                    ERRO
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-400 hidden md:table-cell">
                            {{ $log->response_time_ms ? $log->response_time_ms . 'ms' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            {{ $log->created_at->diffForHumans() }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                            Nenhum stream resolvido ainda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
