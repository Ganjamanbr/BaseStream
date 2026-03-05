@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('content.filmes') }}" class="text-gray-400 hover:text-white text-xs">← Filmes</a>
        <h1 class="text-2xl font-bold mt-1">🎬 {{ $genreName }}</h1>
    </div>
</div>

@if (empty($movies))
    <div class="glass rounded-2xl border border-purple-500/10 p-12 text-center">
        <p class="text-gray-400 text-lg">Nenhum filme encontrado neste gênero.</p>
    </div>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach ($movies as $movie)
            <a href="{{ route('content.details', ['d' => \App\Http\Controllers\ContentController::encodeItem($movie, 'filmes')]) }}"
               class="card-hover glass rounded-xl border border-purple-500/10 overflow-hidden group">
                <div class="aspect-[2/3] relative overflow-hidden">
                    @if (!empty($movie['thumbnail']))
                        <img src="{{ $movie['thumbnail'] }}"
                             alt="{{ $movie['name'] }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             loading="lazy"
                             onerror="this.parentElement.classList.add('thumb-fallback'); this.remove();">
                    @else
                        <div class="w-full h-full thumb-fallback flex items-center justify-center text-3xl text-purple-500/50">🎬</div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </div>
                <div class="p-2">
                    <p class="text-xs sm:text-sm font-medium text-gray-200 line-clamp-2">{{ $movie['name'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endif

@endsection
