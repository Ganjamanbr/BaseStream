@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-xs">← Início</a>
        <h1 class="text-2xl font-bold mt-1">{{ $name }}</h1>
    </div>
</div>

{{-- Card principal --}}
<div class="glass rounded-2xl border border-purple-500/10 overflow-hidden mb-8">
    <div class="flex flex-col sm:flex-row">
        {{-- Poster --}}
        @if (!empty($thumbnail))
            <div class="sm:w-64 flex-shrink-0">
                <img src="{{ $thumbnail }}"
                     alt="{{ $name }}"
                     class="w-full sm:h-full object-cover"
                     onerror="this.parentElement.style.display='none'">
            </div>
        @endif

        {{-- Info --}}
        <div class="p-6 flex-1">
            <h2 class="text-xl font-bold text-white mb-2">{{ $name }}</h2>

            @if (!empty($info))
                <p class="text-gray-400 text-sm mb-4">{{ $info }}</p>
            @endif

            @if (!empty($category))
                <span class="inline-block text-xs bg-purple-500/20 text-purple-300 px-3 py-1 rounded-full mb-4">
                    {{ ucfirst($category) }}
                </span>
            @endif
        </div>
    </div>
</div>

{{-- Fontes disponíveis --}}
<h2 class="text-lg font-semibold text-gray-300 mb-4">Fontes Disponíveis</h2>

@if (empty($sources))
    {{-- Sem fontes parseadas, tenta link direto --}}
    <div class="glass rounded-xl border border-purple-500/10 p-4">
        <a href="{{ route('content.play', ['link' => $link, 'name' => $name, 'thumbnail' => $thumbnail ?? '']) }}"
           class="flex items-center gap-3 hover:bg-purple-500/10 rounded-lg p-2 transition-colors">
            <span class="text-2xl">▶️</span>
            <div>
                <p class="text-sm font-medium text-white">Reproduzir</p>
                <p class="text-xs text-gray-500">Fonte padrão</p>
            </div>
        </a>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ($sources as $i => $source)
            <a href="{{ route('content.play', ['link' => $source['url'], 'name' => $name . ' - Fonte ' . ($i + 1), 'thumbnail' => $thumbnail ?? '']) }}"
               class="card-hover glass rounded-xl border border-purple-500/10 p-4 flex items-center gap-3 group">
                <span class="text-2xl">▶️</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white group-hover:text-purple-300 transition-colors">
                        {{ $source['label'] ?? 'Fonte ' . ($i + 1) }}
                    </p>
                    <p class="text-xs text-gray-500 truncate">
                        {{ $source['host'] ?? parse_url($source['url'], PHP_URL_HOST) ?? 'Desconhecido' }}
                    </p>
                </div>
                <span class="text-[10px] bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">PLAY</span>
            </a>
        @endforeach
    </div>
@endif

@endsection
