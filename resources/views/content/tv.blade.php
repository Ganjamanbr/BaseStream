@extends('content.layout')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <span>📺</span>
        <span>TV ao Vivo</span>
    </h1>
    <a href="{{ route('content.index') }}" class="text-gray-400 hover:text-white text-sm">← Voltar</a>
</div>

@if (empty($channels))
    <div class="glass rounded-2xl border border-purple-500/10 p-12 text-center">
        <p class="text-gray-400 text-lg">Nenhum canal disponível.</p>
    </div>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach ($channels as $ch)
            @php
                $link = $ch['link'] ?? '';
                $hasDirectStream = str_contains($link, 'chresolver1=')
                    || str_contains($link, 'pluto=')
                    || preg_match('/^https?:\/\/.+\.(m3u8|ts|mp4)/i', $link);
            @endphp

            @if ($hasDirectStream)
                <a href="{{ route('content.play', ['link' => $link, 'name' => $ch['name'], 'thumbnail' => $ch['thumbnail'] ?? '']) }}"
                   class="card-hover glass rounded-xl border border-purple-500/10 p-3 flex flex-col items-center text-center group">
            @else
                <a href="{{ route('content.details', ['name' => $ch['name'], 'link' => $link, 'thumbnail' => $ch['thumbnail'] ?? '', 'category' => 'tv']) }}"
                   class="card-hover glass rounded-xl border border-purple-500/10 p-3 flex flex-col items-center text-center group">
            @endif
                    {{-- Logo --}}
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden mb-2 flex-shrink-0">
                        @if (!empty($ch['thumbnail']))
                            <img src="{{ $ch['thumbnail'] }}"
                                 alt="{{ $ch['name'] }}"
                                 class="w-full h-full object-contain bg-black/30"
                                 loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full thumb-fallback flex items-center justify-center text-2xl\'>📺</div>';">
                        @else
                            <div class="w-full h-full thumb-fallback flex items-center justify-center text-2xl">📺</div>
                        @endif
                    </div>

                    <p class="text-xs sm:text-sm font-medium text-gray-200 line-clamp-2">{{ $ch['name'] }}</p>

                    @if ($hasDirectStream)
                        <span class="mt-1 text-[10px] bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">AO VIVO</span>
                    @else
                        <span class="mt-1 text-[10px] bg-purple-500/20 text-purple-400 px-2 py-0.5 rounded-full">CATEGORIA</span>
                    @endif
                </a>
        @endforeach
    </div>
@endif

@endsection
