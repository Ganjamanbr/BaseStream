@extends('content.layout')
@section('content')

{{-- Busca mobile --}}
<div class="sm:hidden mb-6">
    <form action="{{ route('content.search') }}" method="GET" class="flex">
        <input type="text" name="q" placeholder="Buscar conteúdo..."
               class="flex-1 bg-white/10 border border-purple-500/30 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-400">
        <button type="submit" class="ml-2 bg-purple-600 hover:bg-purple-500 px-4 py-2 rounded-lg text-sm">🔍</button>
    </form>
</div>

{{-- Hero --}}
<div class="text-center mb-10">
    <h1 class="text-3xl sm:text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 via-pink-400 to-blue-400 bg-clip-text text-transparent">
        BaseStream
    </h1>
    <p class="text-gray-400 text-sm">Escolha uma categoria para explorar</p>
</div>

{{-- Grid de categorias --}}
@php
    $routeMap = \App\Services\BrazucaContentService::CATEGORY_ROUTES;
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
    @foreach ($categories as $cat)
        <a href="{{ route($routeMap[$cat['id']] ?? 'content.index') }}"
           class="card-hover glass rounded-2xl border border-purple-500/10 p-6 sm:p-8 text-center group">
            <div class="text-4xl sm:text-5xl mb-3">{{ $cat['icon'] }}</div>
            <h2 class="text-lg font-semibold text-white group-hover:text-purple-300 transition-colors">
                {{ $cat['name'] }}
            </h2>
            <p class="text-xs text-gray-500 mt-1">{{ $cat['description'] ?? '' }}</p>
        </a>
    @endforeach
</div>

@endsection
