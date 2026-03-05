@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <span>🎬</span>
        <span>Filmes</span>
    </h1>
    <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-sm">← Voltar</a>
</div>

{{-- Link para lançamentos --}}
<div class="mb-6">
    <a href="{{ route('content.filmes.lancamentos') }}"
       class="card-hover inline-flex items-center gap-2 glass rounded-xl border border-yellow-500/20 px-5 py-3 hover:border-yellow-500/40 transition-colors">
        <span class="text-xl">🌟</span>
        <span class="font-semibold text-yellow-300">Lançamentos</span>
    </a>
</div>

{{-- Grid de gêneros --}}
<h2 class="text-lg font-semibold text-gray-300 mb-4">Gêneros</h2>

@if (empty($genres))
    <div class="glass rounded-2xl border border-purple-500/10 p-12 text-center">
        <p class="text-gray-400 text-lg">Nenhum gênero disponível.</p>
    </div>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
        @foreach ($genres as $genre)
            <a href="{{ route('content.filmes.genre', ['genero' => $genre['slug']]) }}"
               class="card-hover glass rounded-xl border border-purple-500/10 p-5 text-center group">
                <div class="text-2xl mb-2">{{ $genre['icon'] ?? '🎬' }}</div>
                <p class="text-sm font-medium text-gray-200 group-hover:text-purple-300 transition-colors">
                    {{ $genre['name'] }}
                </p>
            </a>
        @endforeach
    </div>
@endif

@endsection
