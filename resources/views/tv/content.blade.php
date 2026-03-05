@extends('tv.layout', ['pageTitle' => $title ?? 'Conteúdo'])

@push('styles')
<style>
.content-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.content-header-icon { font-size: 4rem; }

.content-header-title {
    font-size: var(--font-lg);
    font-weight: 900;
    color: var(--text);
}

.content-header-count {
    font-size: var(--font-sm);
    color: var(--text-dim);
    margin-left: auto;
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 2rem;
    padding-bottom: 3rem;
}

.item-card {
    background: var(--card);
    border: 2px solid var(--border);
    border-radius: 1.2rem;
    overflow: hidden;
    cursor: none;
    text-decoration: none;
    color: var(--text);
    display: flex;
    flex-direction: column;
    transition: transform 0.12s, border-color 0.12s, box-shadow 0.12s;
    outline: none;
    position: relative;
}

.item-card:focus,
.item-card.focused {
    border-color: var(--focus);
    transform: scale(1.06);
    box-shadow: 0 0 0 3px var(--focus), 0 0 30px var(--focus-glow);
    z-index: 2;
}

.item-thumb-wrap {
    width: 100%;
    aspect-ratio: 16/9;
    background: #111128;
    overflow: hidden;
    position: relative;
}

.item-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: opacity 0.3s;
}

.item-thumb.lazy { opacity: 0; }
.item-thumb.loaded { opacity: 1; }

.item-thumb-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: var(--text-dim);
    background: var(--card);
}

.item-info {
    padding: 1.2rem 1.4rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.item-name {
    font-size: 1.6rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-meta {
    font-size: 1.4rem;
    color: var(--text-dim);
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.badge {
    background: rgba(124,58,237,0.25);
    color: #a78bfa;
    border-radius: 0.6rem;
    padding: 0.2rem 0.6rem;
    font-size: 1.2rem;
    font-weight: 600;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 8rem;
    color: var(--text-dim);
    font-size: var(--font-sm);
}

/* live channel layout variant */
.live-grid {
    grid-template-columns: repeat(6, 1fr);
}

.live-grid .item-thumb-wrap {
    aspect-ratio: 1/1;
}
</style>
@endpush

@section('content')
<div class="scroll-area">
    <div class="content-header">
        @if(isset($icon))
            <span class="content-header-icon">{{ $icon }}</span>
        @endif
        <span class="content-header-title">{{ $title ?? 'Conteúdo' }}</span>
        @if(isset($items) && count($items))
            <span class="content-header-count">{{ count($items) }} {{ count($items) === 1 ? 'item' : 'itens' }}</span>
        @endif
    </div>

    <div class="items-grid {{ ($type ?? '') === 'live' ? 'live-grid' : '' }}">
        @forelse ($items ?? [] as $item)
            @php
                $streamLink = $item['stream_url'] ?? $item['url'] ?? '';
                $itemTitle  = $item['name'] ?? $item['title'] ?? '';
                $thumb      = $item['stream_icon'] ?? $item['cover'] ?? $item['logo'] ?? $item['backdrop'] ?? '';
                $category   = $item['category_name'] ?? $item['genre'] ?? '';
                $playerUrl  = route('tv.player') 
                    . '?link=' . urlencode($streamLink) 
                    . '&title=' . urlencode($itemTitle)
                    . ($thumb ? '&thumb=' . urlencode($thumb) : '');
                $iconFallback = match($type ?? '') {
                    'live'   => '📡',
                    'movie'  => '🎬',
                    'series' => '📺',
                    'anime'  => '🎌',
                    'novela' => '💃',
                    'desenho'=> '🎨',
                    'dorama' => '🇰🇷',
                    default  => '▶️',
                };
            @endphp
            <a href="{{ $playerUrl }}"
               class="item-card"
               data-focusable
               tabindex="0">
                <div class="item-thumb-wrap">
                    @if($thumb)
                        <img class="item-thumb lazy"
                             data-src="{{ $thumb }}"
                             alt="{{ $itemTitle }}"
                             loading="lazy"
                             onerror="this.style.display='none'">
                    @endif
                    <div class="item-thumb-placeholder">{{ $iconFallback }}</div>
                </div>
                <div class="item-info">
                    <div class="item-name" title="{{ $itemTitle }}">{{ $itemTitle }}</div>
                    @if($category)
                        <div class="item-meta">
                            <span class="badge">{{ $category }}</span>
                        </div>
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                😕 Nenhum conteúdo disponível no momento.<br>
                <small>Verifique sua conexão ou tente outro catálogo.</small>
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
// Lazy load thumbnails after render
(function() {
    const imgs = document.querySelectorAll('.item-thumb.lazy');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '100px' });
        imgs.forEach(function(img) { observer.observe(img); });
    } else {
        imgs.forEach(function(img) {
            img.src = img.dataset.src;
            img.classList.add('loaded');
        });
    }
})();
</script>
@endpush
