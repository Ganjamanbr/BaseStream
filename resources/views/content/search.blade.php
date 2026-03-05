@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <span>🔍</span>
        <span>Busca</span>
    </h1>
    <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-sm">← Voltar</a>
</div>

{{-- Campo de busca --}}
<form action="{{ route('content.search') }}" method="GET" class="mb-6">
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $query }}" placeholder="Buscar séries, filmes, animes..."
               class="flex-1 bg-white/10 border border-purple-500/30 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-400"
               autofocus>
        <button type="submit" class="bg-purple-600 hover:bg-purple-500 px-6 py-3 rounded-xl text-sm font-medium transition-colors">
            Buscar
        </button>
    </div>
</form>

{{-- Resultados --}}
<div id="search-results">
    @include('content.partials.search-results', ['results' => $results, 'query' => $query])
</div>

@endsection
