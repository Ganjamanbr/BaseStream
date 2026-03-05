<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'BaseStream' }} - TV</title>

    <style>
    /* ── Reset & Base ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --bg:         #060610;
        --bg2:        #0e0e1f;
        --card:       #16162a;
        --card-hover: #1e1e38;
        --border:     rgba(124,58,237,0.25);
        --accent:     #7c3aed;
        --accent2:    #a855f7;
        --focus:      #a855f7;
        --focus-glow: rgba(168,85,247,0.6);
        --text:       #f1f1f8;
        --text-dim:   #9090b0;
        --red:        #ef4444;
        --safe:       5vw;   /* tv safe zone */
        --font-xs:    1.6rem;
        --font-sm:    2rem;
        --font-md:    2.4rem;
        --font-lg:    3rem;
        --font-xl:    4rem;
    }

    html, body {
        width: 100%; height: 100%;
        background: var(--bg);
        color: var(--text);
        font-family: 'Helvetica Neue', Arial, sans-serif;
        font-size: 16px;
        overflow: hidden;
        cursor: none; /* no mouse cursor on TV */
        -webkit-font-smoothing: antialiased;
    }

    /* ── TV Safe Area ── */
    #tv-app {
        position: fixed;
        inset: 0;
        padding: var(--safe);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* ── Top Bar ── */
    #topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 3vh;
        flex-shrink: 0;
    }

    .logo {
        font-size: var(--font-lg);
        font-weight: 900;
        background: linear-gradient(135deg, #a855f7, #ec4899);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -1px;
    }

    .logo span { -webkit-text-fill-color: #7c3aed; }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 2rem;
        font-size: var(--font-xs);
        color: var(--text-dim);
    }

    /* ── Nav tabs ── */
    #nav {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 3vh;
        flex-shrink: 0;
    }

    .nav-tab {
        padding: 0.8rem 2rem;
        border-radius: 50px;
        font-size: var(--font-xs);
        font-weight: 600;
        color: var(--text-dim);
        background: var(--bg2);
        border: 2px solid transparent;
        text-decoration: none;
        transition: all 0.15s;
        outline: none;
        cursor: none;
        white-space: nowrap;
    }

    .nav-tab:focus,
    .nav-tab.focused {
        color: var(--text);
        border-color: var(--focus);
        background: rgba(168,85,247,0.15);
        box-shadow: 0 0 20px var(--focus-glow);
    }

    .nav-tab.active {
        color: white;
        background: var(--accent);
        border-color: var(--accent2);
    }

    /* ── Main content area ── */
    #main {
        flex: 1;
        overflow: hidden;
        position: relative;
    }

    /* ── Focus ring (global) ── */
    [data-focusable]:focus,
    [data-focusable].focused {
        outline: none;
        border-color: var(--focus) !important;
        box-shadow: 0 0 0 3px var(--focus), 0 0 30px var(--focus-glow) !important;
    }

    /* ── Loading spinner ── */
    .spinner {
        width: 6rem; height: 6rem;
        border: 5px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Clock ── */
    #clock {
        font-size: var(--font-sm);
        font-weight: 700;
        color: var(--text);
        font-variant-numeric: tabular-nums;
    }

    /* ── Remote hint ── */
    .remote-hint {
        font-size: 1.4rem;
        color: var(--text-dim);
        opacity: 0.6;
    }
    .remote-hint span {
        display: inline-block;
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.2em 0.5em;
        margin: 0 0.2em;
        font-family: monospace;
    }

    /* ── Scrollable grid wrapper ── */
    .scroll-area {
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding-right: 1rem;
        scroll-behavior: smooth;
    }
    .scroll-area::-webkit-scrollbar { display: none; }

    /* ── Category section heading ── */
    .section-heading {
        font-size: var(--font-sm);
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 1.5rem;
        padding-left: 0.5rem;
        border-left: 4px solid var(--accent);
    }

    /* Ensure focus is always visible on dark backgrounds */
    *:focus-visible {
        outline: 3px solid var(--focus);
        outline-offset: 3px;
    }
    </style>

    @stack('styles')
</head>
<body>
<div id="tv-app">

    {{-- ── Top Bar ── --}}
    <div id="topbar">
        <div class="logo">Base<span>Stream</span></div>
        <div class="topbar-right">
            <div id="clock">--:--</div>
            @unless($hideRemoteHint ?? false)
            <div class="remote-hint">
                <span>▲▼◀▶</span> Navegar &nbsp;
                <span>OK</span> Selecionar &nbsp;
                <span>⟵</span> Voltar
            </div>
            @endunless
        </div>
    </div>

    {{-- ── Nav Tabs ── --}}
    @unless($hideNav ?? false)
    <nav id="nav" role="navigation">
        <a href="{{ route('tv.home') }}"
           class="nav-tab {{ request()->routeIs('tv.home') ? 'active' : '' }}"
           data-focusable tabindex="0">🏠 Início</a>
        <a href="{{ route('tv.live') }}"
           class="nav-tab {{ request()->routeIs('tv.live') ? 'active' : '' }}"
           data-focusable tabindex="0">📡 Ao Vivo</a>
        <a href="{{ route('tv.movies') }}"
           class="nav-tab {{ request()->routeIs('tv.movies') ? 'active' : '' }}"
           data-focusable tabindex="0">🎬 Filmes</a>
        <a href="{{ route('tv.series') }}"
           class="nav-tab {{ request()->routeIs('tv.series') ? 'active' : '' }}"
           data-focusable tabindex="0">📺 Séries</a>
        <a href="{{ route('tv.animes') }}"
           class="nav-tab {{ request()->routeIs('tv.animes') ? 'active' : '' }}"
           data-focusable tabindex="0">🎌 Animes</a>
        <a href="{{ route('tv.novelas') }}"
           class="nav-tab {{ request()->routeIs('tv.novelas') ? 'active' : '' }}"
           data-focusable tabindex="0">💃 Novelas</a>
        <a href="{{ route('tv.doramas') }}"
           class="nav-tab {{ request()->routeIs('tv.doramas') ? 'active' : '' }}"
           data-focusable tabindex="0">🇰🇷 Doramas</a>
        <a href="{{ route('tv.logout') }}"
           class="nav-tab"
           data-focusable tabindex="0">🚪 Sair</a>
    </nav>
    @endunless

    {{-- ── Main Content ── --}}
    <main id="main">
        @yield('content')
    </main>

</div>

{{-- ── Core JS: clock + D-pad navigation ── --}}
<script>
// ── Clock ──────────────────────────────────────────────────
(function clockTick() {
    const clock = document.getElementById('clock');
    function update() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        clock.textContent = h + ':' + m;
    }
    update();
    setInterval(update, 10000);
})();

// ── Samsung Tizen Remote Key Registration ──────────────────
(function registerTizenKeys() {
    if (typeof tizen === 'undefined') return;
    try {
        const keys = [
            'MediaPlayPause','MediaPlay','MediaPause','MediaStop',
            'MediaFastForward','MediaRewind',
            'ChannelUp','ChannelDown',
            'ColorF0Red','ColorF1Green','ColorF2Yellow','ColorF3Blue',
        ];
        keys.forEach(k => {
            try { tizen.tvinputdevice.registerKey(k); } catch(e) {}
        });
    } catch(e) {}
})();

// ── D-pad / Remote Spatial Navigation ─────────────────────
(function initDpad() {
    const KEYS = {
        UP: 38, DOWN: 40, LEFT: 37, RIGHT: 39,
        ENTER: 13, BACK: 8, BACK2: 10009,
        PLAY: 415, PAUSE: 19, STOP: 413, FF: 417, RW: 412,
        RED: 403, GREEN: 404, YELLOW: 405, BLUE: 406,
    };

    let focused = null;

    function getAllFocusable() {
        return Array.from(document.querySelectorAll(
            '[data-focusable], [tabindex="0"], a[href], button:not(:disabled)'
        )).filter(el => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0;
        });
    }

    function setFocus(el) {
        if (!el) return;
        if (focused && focused !== el) {
            focused.classList.remove('focused');
        }
        focused = el;
        el.classList.add('focused');
        el.focus({ preventScroll: false });
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }

    function findNearest(currentEl, direction) {
        const all = getAllFocusable();
        if (!all.length) return null;
        if (!currentEl) return all[0];

        const cr = currentEl.getBoundingClientRect();
        const cx = cr.left + cr.width / 2;
        const cy = cr.top  + cr.height / 2;

        let best = null, bestScore = Infinity;

        all.forEach(el => {
            if (el === currentEl) return;
            const r  = el.getBoundingClientRect();
            const ex = r.left + r.width / 2;
            const ey = r.top  + r.height / 2;
            const dx = ex - cx, dy = ey - cy;

            let inDir = false;
            if (direction === 'up'    && dy < -10) inDir = true;
            if (direction === 'down'  && dy > 10)  inDir = true;
            if (direction === 'left'  && dx < -10) inDir = true;
            if (direction === 'right' && dx > 10)  inDir = true;
            if (!inDir) return;

            // Score: weighted distance (perpendicular axis weighted more)
            let score;
            if (direction === 'up' || direction === 'down') {
                score = Math.abs(dy) + Math.abs(dx) * 3;
            } else {
                score = Math.abs(dx) + Math.abs(dy) * 3;
            }

            if (score < bestScore) { bestScore = score; best = el; }
        });

        return best;
    }

    document.addEventListener('keydown', function(e) {
        switch (e.keyCode) {
            case KEYS.UP:
                e.preventDefault();
                setFocus(findNearest(focused, 'up'));
                break;
            case KEYS.DOWN:
                e.preventDefault();
                setFocus(findNearest(focused, 'down'));
                break;
            case KEYS.LEFT:
                e.preventDefault();
                setFocus(findNearest(focused, 'left'));
                break;
            case KEYS.RIGHT:
                e.preventDefault();
                setFocus(findNearest(focused, 'right'));
                break;
            case KEYS.ENTER:
                e.preventDefault();
                if (focused) focused.click();
                break;
            case KEYS.BACK:
            case KEYS.BACK2:
                e.preventDefault();
                window.history.back();
                break;
        }
    });

    // Initial focus
    document.addEventListener('DOMContentLoaded', function() {
        const all = getAllFocusable();
        if (all.length) setFocus(all[0]);
    });

    // Re-focus on page ready
    if (document.readyState !== 'loading') {
        setTimeout(() => {
            const all = getAllFocusable();
            if (all.length) setFocus(all[0]);
        }, 100);
    }
})();
</script>

@stack('scripts')
</body>
</html>
