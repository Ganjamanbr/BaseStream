@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <span>{{ $icon ?? '📺' }}</span>
        <span>{{ $title }}</span>
    </h1>
    <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-sm">← Voltar</a>
</div>

@if (empty($items))
    <div class="glass rounded-2xl border border-purple-500/10 p-12 text-center">
        <p class="text-gray-400 text-lg">Nenhum conteúdo encontrado.</p>
        <p class="text-gray-600 text-sm mt-2">Tente novamente mais tarde.</p>
    </div>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach ($items as $item)
            <a href="{{ route('content.details', ['d' => \App\Http\Controllers\ContentController::encodeItem($item, $category ?? '')]) }}"
               class="card-hover glass rounded-xl border border-purple-500/10 overflow-hidden group">
                {{-- Thumbnail --}}
                <div class="aspect-[2/3] relative overflow-hidden">
                    @if (!empty($item['thumbnail']))
                        <img src="{{ $item['thumbnail'] }}"
                             alt="{{ $item['name'] }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             loading="lazy"
                             onerror="this.parentElement.classList.add('thumb-fallback'); this.remove();">
                    @else
                        <div class="w-full h-full thumb-fallback flex items-center justify-center text-3xl text-purple-500/50">
                            🎬
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </div>

                {{-- Nome --}}
                <div class="p-2">
                    <p class="text-xs sm:text-sm font-medium text-gray-200 line-clamp-2">{{ $item['name'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endif

@endsection
