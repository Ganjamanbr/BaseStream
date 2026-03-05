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
        <a href="{{ route('content.play', ['d' => \App\Http\Controllers\ContentController::encodeItem(['name' => $name, 'link' => $link, 'thumbnail' => $thumbnail ?? ''])]) }}"
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
            <a href="{{ route('content.play', ['d' => \App\Http\Controllers\ContentController::encodeItem(['name' => $name . ' - Fonte ' . ($i + 1), 'link' => $source['url'], 'thumbnail' => $thumbnail ?? ''])]) }}"
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

{{-- Seletor de episódios (apenas para séries com IMDB ID resolvido) --}}
@if (!empty($imdbId) && !empty($isSeries))
<div class="mt-8">
    <h2 class="text-lg font-semibold text-gray-300 mb-4">📺 Episódios</h2>

    <div class="glass rounded-2xl border border-purple-500/10 p-6">
        <p class="text-xs text-gray-500 mb-5">
            Selecione a temporada e o episódio. O player carrega via
            <span class="text-purple-400">VidSrc</span> com múltiplos servidores disponíveis.
        </p>

        <div class="flex flex-wrap gap-4 items-end mb-6">
            <div>
                <label class="text-xs text-gray-400 mb-1.5 block font-medium">Temporada</label>
                <select id="season-select"
                        class="bg-gray-900 border border-purple-500/30 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-purple-500 min-w-[100px]">
                    @for ($s = 1; $s <= 20; $s++)
                        <option value="{{ $s }}">T{{ $s }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-400 mb-1.5 block font-medium">Episódio</label>
                <select id="episode-select"
                        class="bg-gray-900 border border-purple-500/30 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-purple-500 min-w-[100px]">
                    @for ($e = 1; $e <= 30; $e++)
                        <option value="{{ $e }}">EP{{ $e }}</option>
                    @endfor
                </select>
            </div>

            <button onclick="playEpisode()"
                    class="bg-purple-600 hover:bg-purple-700 active:scale-95 text-white text-sm font-semibold px-6 py-2 rounded-lg transition-all flex items-center gap-2 shadow-lg shadow-purple-500/20">
                <span>▶</span> Assistir Episódio
            </button>
        </div>

        <div>
            <p class="text-xs text-gray-500 mb-3" id="quick-label">Acesso rápido — Temporada 1:</p>
            <div class="flex flex-wrap gap-2" id="quick-episodes">
                @for ($e = 1; $e <= 12; $e++)
                    <button onclick="playDirectEpisode(1, {{ $e }})"
                            class="bg-gray-800 hover:bg-purple-600 border border-purple-500/20 hover:border-purple-500 text-gray-300 hover:text-white text-xs px-3 py-1.5 rounded-lg transition-all">
                        EP{{ $e }}
                    </button>
                @endfor
            </div>
        </div>
    </div>
</div>

<script>
const _imdbId   = @json($imdbId);
const _itemName = @json($name);
const _thumb    = @json($thumbnail ?? '');

function buildEpisodeUrl(season, episode) {
    return `https://vidsrc.cc/v2/embed/tv/${_imdbId}/${season}/${episode}`;
}

function encodePlayItem(season, episode) {
    const label = `${_itemName} — T${String(season).padStart(2,'0')}E${String(episode).padStart(2,'0')}`;
    const payload = JSON.stringify({ n: label, l: buildEpisodeUrl(season, episode), t: _thumb, f: '', i: '', c: 'series' });
    return btoa(unescape(encodeURIComponent(payload)));
}

function playEpisode() {
    const s = parseInt(document.getElementById('season-select').value);
    const e = parseInt(document.getElementById('episode-select').value);
    window.location.href = `/conteudo/play?d=${encodeURIComponent(encodePlayItem(s, e))}`;
}

function playDirectEpisode(season, episode) {
    window.location.href = `/conteudo/play?d=${encodeURIComponent(encodePlayItem(season, episode))}`;
}

document.getElementById('season-select').addEventListener('change', function () {
    const s = parseInt(this.value);
    const grid = document.getElementById('quick-episodes');
    document.getElementById('quick-label').textContent = `Acesso rápido — Temporada ${s}:`;
    grid.innerHTML = '';
    for (let e = 1; e <= 12; e++) {
        const btn = document.createElement('button');
        btn.textContent = `EP${e}`;
        btn.className = 'bg-gray-800 hover:bg-purple-600 border border-purple-500/20 hover:border-purple-500 text-gray-300 hover:text-white text-xs px-3 py-1.5 rounded-lg transition-all';
        btn.onclick = () => playDirectEpisode(s, e);
        grid.appendChild(btn);
    }
});
</script>
@endif

@endsection
