@extends('tv.layout', ['pageTitle' => 'Início'])

@push('styles')
<style>
.home-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2.4rem;
    padding-bottom: 2rem;
}

.cat-card {
    background: var(--card);
    border: 3px solid var(--border);
    border-radius: 2rem;
    padding: 3.5rem 2rem;
    text-align: center;
    cursor: none;
    text-decoration: none;
    color: var(--text);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    transition: background 0.15s, border-color 0.15s, transform 0.12s;
    outline: none;
    position: relative;
    overflow: hidden;
}

.cat-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--glow-color, rgba(124,58,237,0.08));
    opacity: 0;
    transition: opacity 0.15s;
}

.cat-card:focus,
.cat-card.focused {
    border-color: var(--focus) !important;
    transform: scale(1.05);
    background: var(--card-hover);
    box-shadow: 0 0 0 3px var(--focus), 0 0 40px var(--focus-glow);
}

.cat-card:focus::before,
.cat-card.focused::before {
    opacity: 1;
}

.cat-icon {
    font-size: 5rem;
    line-height: 1;
}

.cat-name {
    font-size: var(--font-sm);
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.cat-desc {
    font-size: 1.6rem;
    color: var(--text-dim);
}

.cat-dot {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    margin-top: 0.5rem;
}

.page-title {
    font-size: var(--font-lg);
    font-weight: 900;
    color: var(--text);
    margin-bottom: 3rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}
</style>
@endpush

@section('content')
<div class="scroll-area">
    <div class="page-title">
        🏠 <span>Início</span>
        <span style="font-size:var(--font-sm);color:var(--text-dim);font-weight:400;margin-left:auto;">
            Bem-vindo à BaseStream
        </span>
    </div>

    <div class="home-grid">
        @php
            $routeMap = [
                'ao-vivo'  => 'tv.live',
                'filmes'   => 'tv.movies',
                'series'   => 'tv.series',
                'animes'   => 'tv.animes',
                'novelas'  => 'tv.novelas',
                'desenhos' => 'tv.desenhos',
                'doramas'  => 'tv.doramas',
            ];
        @endphp
        @foreach ($categories as $cat)
            <a href="{{ route($routeMap[$cat['id']] ?? 'tv.home') }}"
               class="cat-card"
               style="--glow-color: {{ $cat['color'] }}22;"
               data-focusable
               tabindex="0">
                <div class="cat-dot" style="background:{{ $cat['color'] }};box-shadow:0 0 12px {{ $cat['color'] }}88;"></div>
                <div class="cat-icon">{{ $cat['icon'] }}</div>
                <div class="cat-name">{{ $cat['name'] }}</div>
                <div class="cat-desc">{{ $cat['desc'] }}</div>
            </a>
        @endforeach
    </div>
</div>
@endsection
