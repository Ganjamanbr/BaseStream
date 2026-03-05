@if (empty($query))
    <p class="text-gray-500 text-sm">Digite pelo menos 2 caracteres para buscar.</p>
@elseif (empty($results))
    <div class="glass rounded-2xl border border-purple-500/10 p-8 text-center">
        <p class="text-gray-400 text-lg">Nenhum resultado para "{{ $query }}"</p>
        <p class="text-gray-600 text-sm mt-2">Tente outro termo.</p>
    </div>
@else
    <p class="text-gray-400 text-sm mb-4">{{ count($results) }} resultado(s) para "{{ $query }}"</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach ($results as $item)
            <a href="{{ route('content.details', ['d' => \App\Http\Controllers\ContentController::encodeItem($item, $item['category'] ?? '')]) }}"
               class="card-hover glass rounded-xl border border-purple-500/10 overflow-hidden group">
                <div class="aspect-[2/3] relative overflow-hidden">
                    @if (!empty($item['thumbnail']))
                        <img src="{{ $item['thumbnail'] }}"
                             alt="{{ $item['name'] }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             loading="lazy"
                             onerror="this.parentElement.classList.add('thumb-fallback'); this.remove();">
                    @else
                        <div class="w-full h-full thumb-fallback flex items-center justify-center text-3xl text-purple-500/50">🎬</div>
                    @endif

                    @if (!empty($item['category']))
                        <span class="absolute top-2 right-2 text-[10px] bg-purple-500/60 text-white px-2 py-0.5 rounded-full">
                            {{ ucfirst($item['category']) }}
                        </span>
                    @endif
                </div>
                <div class="p-2">
                    <p class="text-xs sm:text-sm font-medium text-gray-200 line-clamp-2">{{ $item['name'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endif
