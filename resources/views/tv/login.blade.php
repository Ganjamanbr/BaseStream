<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BaseStream — Login TV</title>
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --bg: #060610; --bg2: #0e0e1f;
        --accent: #7c3aed; --accent2: #a855f7;
        --focus-glow: rgba(168,85,247,0.6);
        --text: #f1f1f8; --text-dim: #9090b0;
        --border: rgba(124,58,237,0.3);
        --font-sm: 2rem; --font-md: 2.4rem; --font-lg: 3.2rem; --font-xl: 4.5rem;
    }

    html, body {
        width: 100%; height: 100%;
        background: var(--bg);
        background-image: radial-gradient(ellipse at 20% 50%, rgba(124,58,237,0.12) 0%, transparent 60%),
                          radial-gradient(ellipse at 80% 20%, rgba(236,72,153,0.08) 0%, transparent 60%);
        color: var(--text);
        font-family: 'Helvetica Neue', Arial, sans-serif;
        display: flex; align-items: center; justify-content: center;
        cursor: none;
        -webkit-font-smoothing: antialiased;
    }

    .login-box {
        width: 60rem;
        max-width: 90vw;
        background: var(--bg2);
        border: 2px solid var(--border);
        border-radius: 2.5rem;
        padding: 5rem 6rem;
        text-align: center;
        box-shadow: 0 0 80px rgba(124,58,237,0.15);
    }

    .logo {
        font-size: var(--font-xl);
        font-weight: 900;
        background: linear-gradient(135deg, #a855f7, #ec4899);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 0.5rem;
    }

    .logo span { -webkit-text-fill-color: #7c3aed; }

    .subtitle {
        font-size: var(--font-sm);
        color: var(--text-dim);
        margin-bottom: 5rem;
    }

    .field {
        margin-bottom: 2.5rem;
        text-align: left;
    }

    .field label {
        display: block;
        font-size: var(--font-sm);
        color: var(--text-dim);
        margin-bottom: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .field input {
        width: 100%;
        background: rgba(255,255,255,0.05);
        border: 3px solid var(--border);
        border-radius: 1.2rem;
        padding: 1.4rem 2rem;
        font-size: var(--font-md);
        color: var(--text);
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        font-family: inherit;
    }

    .field input:focus, .field input.focused {
        border-color: var(--accent2);
        box-shadow: 0 0 0 3px var(--accent), 0 0 30px var(--focus-glow);
    }

    .btn-login {
        width: 100%;
        background: linear-gradient(135deg, var(--accent), #ec4899);
        border: none;
        border-radius: 1.2rem;
        padding: 1.6rem;
        font-size: var(--font-md);
        font-weight: 700;
        color: white;
        cursor: none;
        outline: none;
        transition: box-shadow 0.15s, transform 0.1s;
        margin-top: 1.5rem;
        font-family: inherit;
    }

    .btn-login:focus, .btn-login.focused {
        box-shadow: 0 0 0 4px var(--accent), 0 0 40px var(--focus-glow);
        transform: scale(1.02);
    }

    .error-msg {
        background: rgba(239,68,68,0.15);
        border: 2px solid rgba(239,68,68,0.4);
        border-radius: 1rem;
        padding: 1.2rem 2rem;
        color: #fca5a5;
        font-size: var(--font-sm);
        margin-bottom: 2.5rem;
    }

    .help-text {
        font-size: 1.6rem;
        color: var(--text-dim);
        margin-top: 3rem;
        line-height: 1.8;
    }

    .help-text strong { color: var(--accent2); }
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">Base<span>Stream</span></div>
    <p class="subtitle">Smart TV</p>

    @if ($errors->any())
        <div class="error-msg">❌ {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('tv.login.post') }}" id="login-form">
        @csrf
        <div class="field">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username"
                   value="{{ old('username') }}"
                   autocomplete="username"
                   data-focusable tabindex="0"
                   placeholder="bs_xxxxxx">
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password"
                   data-focusable tabindex="0"
                   placeholder="••••••••••••••••">
        </div>
        <button type="submit" class="btn-login" data-focusable tabindex="0">
            ▶ ENTRAR
        </button>
    </form>

    <p class="help-text">
        Credenciais geradas em<br>
        <strong>dashboard → 📺 TV Apps</strong>
    </p>
</div>

<script>
// D-pad basic navigation for login screen
(function() {
    var focusables = [];
    var current = 0;

    function refresh() {
        focusables = Array.from(document.querySelectorAll('[data-focusable]'));
    }

    function focus(idx) {
        if (!focusables.length) return;
        if (idx < 0) idx = 0;
        if (idx >= focusables.length) idx = focusables.length - 1;
        current = idx;
        focusables.forEach((el, i) => {
            el.classList.toggle('focused', i === idx);
        });
        focusables[idx].focus();
    }

    document.addEventListener('DOMContentLoaded', function() {
        refresh();
        focus(0);
    });

    document.addEventListener('keydown', function(e) {
        switch(e.keyCode) {
            case 38: // UP
                e.preventDefault(); focus(current - 1); break;
            case 40: // DOWN
                e.preventDefault(); focus(current + 1); break;
            case 13: // ENTER
                e.preventDefault();
                if (focusables[current]) {
                    if (focusables[current].type === 'submit') {
                        document.getElementById('login-form').submit();
                    } else {
                        focusables[current].click();
                    }
                }
                break;
        }
    });
})();
</script>
</body>
</html>
