@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <span>🆓</span>
        <span>Pluto TV</span>
    </h1>
    <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-sm">← Voltar</a>
</div>

<p class="text-gray-400 text-sm mb-6">Canais gratuitos do Pluto TV — conteúdo legal e sem custo.</p>

@if (empty($grouped))
    <div class="glass rounded-2xl border border-purple-500/10 p-12 text-center">
        <p class="text-gray-400 text-lg">Nenhum canal Pluto TV disponível.</p>
    </div>
@else
    @foreach ($grouped as $category => $channels)
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-purple-300 mb-3 border-b border-purple-500/20 pb-2">
                {{ $category }}
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                @foreach ($channels as $ch)
                    <a href="{{ route('content.play', ['link' => $ch['url'], 'name' => $ch['name'], 'thumbnail' => $ch['thumbnail'] ?? '']) }}"
                       class="card-hover glass rounded-xl border border-purple-500/10 p-3 flex flex-col items-center text-center group">

                        <div class="w-14 h-14 rounded-lg overflow-hidden mb-2 flex-shrink-0">
                            @if (!empty($ch['thumbnail']))
                                <img src="{{ $ch['thumbnail'] }}"
                                     alt="{{ $ch['name'] }}"
                                     class="w-full h-full object-contain bg-black/30"
                                     loading="lazy"
                                     onerror="this.src=''">
                            @else
                                <div class="w-full h-full thumb-fallback flex items-center justify-center text-xl">📺</div>
                            @endif
                        </div>

                        <p class="text-xs font-medium text-gray-200 line-clamp-2">{{ $ch['name'] }}</p>
                        <span class="mt-1 text-[10px] bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded-full">GRÁTIS</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

@endsection
