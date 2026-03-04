# BaseStream Proxy - Engineering Blueprint v1.0

 **Data** : 03/Mar/2026 |  **Autor** : Italo Antonio (italoantonio-dev) |  **Status** : MVP Planning

## 🎯 Product Vision

SaaS IPTV "Netflix pessoal" que replica **exatamente** a lógica do addon **BrazucaPlay** via proxy API. Resolve streams dinâmicos (TV BR, filmes dublados, séries/animes) para players simples (Samsung Tizen Smarters, VLC, etc). Multi-device auth, dashboard Netflix-like, tiers free/paid.  **Legal** : Disclaimer + foco streams públicos + Xtream oficial.

 **MVP Goal** : Dia 7 → `https://basestream.railway.app/stream?id=globo` funciona na TV Samsung.

## 📋 User Stories (MoSCoW Prioritization)

## 🚀 **Must Have** (MVP Core - 80% value)

* [US-001] Como usuário, login via email/senha e gero tokens nomeados ("Samsung TV6", "PC") **M**
* [US-002] Como dev/TV, acesso `/stream?id=globo&quality=HD` → retorna HLS proxy resolvido **M**
* [US-003] Como admin, vejo dashboard logs (tokens ativos, streams resolvidos, uptime) **M**
* [US-004] Como player, streams proxy bypass CORS + cache 1h Redis **M**
* [US-005] Como usuário Pro, pago Stripe → ativo 5+ devices **M**

## ✅ **Should Have** (MVP+ - Semana 2)

* [US-006] Como user, categorias Brazuca (TV BR, Filmes Dub, Séries, Animes) **S**
* [US-007] Como admin, scraper monitoring (sucesso/falha por site) **S**
* [US-008] Como user, watch history sync cross-device **S**

## 🤔 **Could Have** (Mês 2)

* [US-009] Frontend Next.js Netflix grid + carrossel **C**
* [US-010] Xtream Codes import (revenda legal) **C**

## ⏳ **Won't Have** (MVP)

* Mobile APK, Tizen native app, ML recs.

## 🏗️ Decisões Arquiteturais

## **Banco de Dados** : **PostgreSQL 16** (Railway managed)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>Por quê Postgres > MariaDB/SQLite?
</span></span><span>✅ ACID + JSONB nativo (streams metadata)
</span><span>✅ Row Level Security (multi-tenant futuro)
</span><span>✅ TimescaleDB extension (logs métricas)
</span><span>✅ Seu Railway master (WordPress → Postgres fácil)
</span><span>❌ Mongo: Overkill schema-less IPTV
</span><span>Schema:
</span><span>- users: id, email, password_hash, stripe_id
</span><span>- api_tokens: id, user_id, name, token_hash, last_ip, expires_at
</span><span>- stream_logs: id, token_id, stream_id, resolved_url, status, ts
</span><span>- scrapers: id, name, sites_json, success_rate
</span><span></span></code></span></div></div></div></pre>

## **Backend** : **Laravel 11 + PHP 8.3** (Monólito)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>Por quê Laravel > FastAPI/NestJS?
</span></span><span>✅ Seu PHP comfort (Sankhya WP) + Railway 1-click
</span><span>✅ Sanctum API tokens out-of-box multi-device
</span><span>✅ Artisan scaffolding (controllers/scrapers)
</span><span>✅ Horizon queues (scrapers async)
</span><span>Stack:
</span><span>├── Laravel API (auth/scrapers/logs)
</span><span>├── Guzzle + Symfony DomCrawler (scraping)
</span><span>├── Predis (Redis cache)
</span><span>├── FFmpeg (HLS transcode)
</span><span></span></code></span></div></div></div></pre>

## **Frontend** : **HTMX + Tailwind** (Server-rendered)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>Por quê HTMX > Next.js/React?
</span></span><span>✅ Zero bundle, 100kb total (TV Samsung leve)
</span><span>✅ Laravel Blade + Alpine.js (Netflix cards)
</span><span>✅ No SPA complexity (Tizen browser friendly)
</span><span>Dashboard: /dashboard → HTMX polls logs
</span><span></span></code></span></div></div></div></pre>

## **Arquitetura** : **Clean Hexagonal** (Modular)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>domain/          # Pure business (ScraperInterface)
</span></span><span>application/     # UseCases (ResolveStreamUseCase)
</span><span>infrastructure/  # Laravel/Guzzle adapters
</span><span></span></code></span></div></div></div></pre>

 **Mono-repo** : ✅ Sim (Laravel + frontend + Docker).

## **Infra** : **Railway + Docker Compose**

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>railway.yml:
</span></span><span>services:
</span><span>  app:
</span><span>    image: php:8.3-fpm
</span><span>    deploy:
</span><span>      startCommand: php artisan serve --host=0.0.0.0
</span><span>  nginx:
</span><span>    image: nginx:alpine
</span><span>  postgres:
</span><span>    image: postgres:16
</span><span>  redis:
</span><span>    image: redis:alpine
</span><span></span></code></span></div></div></div></pre>

## 📦 Dependências + Configs Iniciais

## **composer.json** (Principais)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">json</div></div><div><span><code><span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token property">"require"</span><span class="token token operator">:</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token property">"laravel/framework"</span><span class="token token operator">:</span><span></span><span class="token token">"^11.0"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"laravel/sanctum"</span><span class="token token operator">:</span><span></span><span class="token token">"^4.0"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"guzzlehttp/guzzle"</span><span class="token token operator">:</span><span></span><span class="token token">"^7.8"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"symfony/dom-crawler"</span><span class="token token operator">:</span><span></span><span class="token token">"^7.0"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"predis/predis"</span><span class="token token operator">:</span><span></span><span class="token token">"^2.2"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"laravel/horizon"</span><span class="token token operator">:</span><span></span><span class="token token">"^5.24"</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token property">"spatie/laravel-ignition"</span><span class="token token operator">:</span><span></span><span class="token token">"^2.4"</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## **docker-compose.yml** (Railway compatível)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>version: '3.8'
</span></span><span>services:
</span><span>  app:
</span><span>    build: .
</span><span>    volumes:
</span><span>      - .:/var/www/html
</span><span>    depends_on:
</span><span>      - postgres
</span><span>      - redis
</span><span>  nginx:
</span><span>    image: nginx:alpine
</span><span>    ports:
</span><span>      - "80:80"
</span><span>    volumes:
</span><span>      - ./nginx.conf:/etc/nginx/conf.d/default.conf
</span><span>  postgres:
</span><span>    image: postgres:16
</span><span>    environment:
</span><span>      POSTGRES_DB: italostream
</span><span>      POSTGRES_USER: postgres
</span><span>      POSTGRES_PASSWORD: ${DB_PASSWORD}
</span><span>    volumes:
</span><span>      - pgdata:/var/lib/postgresql/data
</span><span>  redis:
</span><span>    image: redis:alpine
</span><span>volumes:
</span><span>  pgdata:
</span><span></span></code></span></div></div></div></pre>

## **nginx.conf** (HLS Proxy)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>server {
</span></span><span>    listen 80;
</span><span>    location /stream/ {
</span><span>        proxy_pass http://app:9000;
</span><span>        proxy_set_header Host $host;
</span><span>        add_header Access-Control-Allow-Origin *;
</span><span>    }
</span><span>    location /hls/ {
</span><span>        proxy_cache my_cache;
</span><span>        proxy_pass http://ffmpeg;
</span><span>        proxy_cache_valid 200 1h;
</span><span>    }
</span><span>}
</span><span></span></code></span></div></div></div></pre>

## **.env** Template

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>APP_NAME=ItaloStream
</span></span><span>DB_CONNECTION=pgsql
</span><span>DB_HOST=postgres
</span><span>REDIS_HOST=redis
</span><span>STRIPE_KEY=pk_test_...
</span><span>SANCTUM_STATEFUL_DOMAINS=localhost,italostream.railway.app
</span><span></span></code></span></div></div></div></pre>

## **Migrations Essenciais**

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">bash</div></div><div><span><code><span><span>php artisan make:migration create_api_tokens_table
</span></span><span>php artisan make:migration create_stream_logs_table
</span><span></span></code></span></div></div></div></pre>

## 🎯 DIA 4 — Resultados (04/Mar/2026)

### Status: ✅ 52 testes passando (207 assertions), 0 falhas

### Correções aplicadas:
1. **phpunit.xml**: `CACHE_DRIVER` → `CACHE_STORE=array` (Laravel 11 usa `CACHE_STORE`, não `CACHE_DRIVER`; testes estavam usando Redis em vez de array, causando cache persistente entre runs)
2. **ApiTokenFactory::expired()**: Adicionado `is_active => false` (token expirado não deve contar como ativo)
3. **UserSeederTest**: Sort case-insensitive `->sort(fn($a, $b) => strcasecmp($a, $b))` (`'iPhone 15'` com 'i' minúsculo causava ordem diferente)
4. **TvBrTest "stream offline"**: Mock ajustado para `->withAnyArgs()` (UseCase faz fallback HD→SD→AUTO; mock precisa cobrir todas as qualities)
5. **Limpeza**: Removidos arquivos de debug (`MockDebugTest.php`, `debug_mockery.php`) e `Log::debug()` do `ResolveStreamUseCase`

### Suites de teste:
- Unit/Scrapers/BrazucaTvScraperTest: 7 ✅
- Unit/UseCases/ResolveStreamUseCaseTest: 5 ✅
- Feature/Auth/LoginTest: 6 ✅
- Feature/Auth/TokenCreationTest: 8 ✅
- Feature/Auth/TokenValidationTest: 9 ✅
- Feature/StreamResolution/QualitySelectionTest: 2 ✅
- Feature/StreamResolution/StreamOfflineTest: 2 ✅
- Feature/StreamResolution/TvBrTest: 5 ✅
- Database/StreamLogSeederTest: 2 ✅
- Database/TokenLimitTest: 2 ✅
- Database/UserSeederTest: 4 ✅

## 🎯 DIA 5 — Performance & Otimização (05/Mar/2026)

### Status: ✅ 59 testes passando (231 assertions), 0 falhas

### Implementações:
1. **ResolveStreamJob** — Job assíncrono para resolução em background (retry 3x, backoff exponencial, cache skip)
2. **StreamCache** — Service layer abstraindo Redis cache (get/put/has/forget/ttl/flushExpired/count)
3. **DB Index Migration** — Indexes otimizados para stream_logs e api_tokens
4. **StreamController refatorado** — Background refresh via scheduleBackgroundRefresh() quando TTL < 20%
5. **nginx.conf otimizado** — gzip, cache headers, proxy_cache_background_update
6. **Rate Limiting** — Middleware tier-based (free=10/min, pro=60/min) em AppServiceProvider
7. **Horizon** — Instalado e configurado (HorizonServiceProvider, config/horizon.php)
8. **7 novos testes** — Cache hit, StreamCache CRUD, job dispatch, job cache skip, rate limiter 429, migration

## 🎯 DIA 6 — Interface de Saída: Dashboard Netflix-like + HTMX (06/Mar/2026)

### Status: ✅ 59 testes passando (231 assertions), 0 falhas

### Implementações:

#### 1. Layout Purple/Gradient (`resources/views/dashboard/layout.blade.php`)
- Novo layout base com tema purple/gradient (`from-slate-900 via-purple-900 to-slate-900`)
- Glass morphism (backdrop-blur-16px), card-hover transitions
- Navbar sticky com nav links (Overview, Logs, Devices), tier badge, logout
- HTMX 1.9.12 + Alpine.js 3.x + Tailwind CDN com custom colors (bs-accent: #a855f7, bs-pink: #ec4899, bs-green: #34d399)
- Flash messages (success/error) com auto-dismiss Alpine.js
- Footer com versão

#### 2. Login Page (`resources/views/auth/login.blade.php`)
- Standalone HTML (não estende layout) — purple gradient background
- Form com email/password/remember + CSRF
- Validação server-side com error display area
- Design glassmorphism com inputes rounded-xl e gradient submit button

#### 3. Dashboard Index (`resources/views/dashboard/index.blade.php`)
- Redesign completo: extends `dashboard.layout` (antes `layouts.app`)
- Stats cards HTMX auto-refresh 30s via named route `dashboard.stats.partial`
- Quick Access grid: 6 canais (Globo, SporTV, Band, SBT, Record, Rede Brasil) com cores únicas
- Tokens grid com `<x-token-item>` component
- Logs table HTMX auto-refresh 10s via named route `dashboard.logs.partial`
- Create Token modal com Alpine.js x-transition + glass border

#### 4. Logs Page (`resources/views/dashboard/logs.blade.php`)
- Página dedicada de logs com time-range filters (1h, Hoje, 7d, Tudo) via HTMX
- Mini stats bar (Total, Sucesso, Tempo Médio) computados no Blade
- Tabela HTMX-refreshable 

#### 5. Tokens Page (`resources/views/dashboard/tokens.blade.php`)
- Gerenciamento completo de devices/tokens
- Cards com show/hide token value (Alpine.js x-data spoiler)
- Indicator de status ativo (emerald glow) / inativo (gray)
- Revoke HTMX (hx-delete com hx-target closest, hx-swap outerHTML)
- Empty state com CTA

#### 6. Blade Components
- `resources/views/components/stream-card.blade.php` — Card de acesso rápido para canais (gradient color, link direto)
- `resources/views/components/token-item.blade.php` — Card de device/token reutilizável (stats, revoke button)

#### 7. Partials Atualizadas
- `stats-cards.blade.php` — Purple theme, emojis, glass cards com card-hover
- `logs-table.blade.php` — Purple theme, pills arredondadas, responsive (hidden sm/md columns), suporta `$logs` ou `$recentLogs`

#### 8. DashboardController Atualizado
- `logs()` — Suporta full page E HTMX partial (detecta HX-Request header + time-range filter)
- `logsPartial()` — Novo método para polling HTMX do dashboard index (50 últimos logs)
- `stats()` — Atualizado: retorna `active_tokens`, `max_tokens`, `total_streams`, `success_rate`
- `tokens()` — Novo método: lista todos tokens (ativos + inativos) com streams_count

#### 9. LoginController (`App\Http\Controllers\Auth\LoginController`)
- `showLoginForm()` — GET /login (redirect se já auth)
- `login()` — POST /login (Auth::attempt + session regenerate)
- `logout()` — POST /logout (session invalidate + token regenerate)

#### 10. Routes Atualizadas (`routes/web.php`)
- `GET /login` — `web.login` (guest middleware)
- `POST /login` — `web.login.submit` (guest middleware)
- `POST /logout` — `web.logout` (auth middleware)
- `GET /dashboard/logs` — `dashboard.logs` (full page + HTMX partial)
- `GET /dashboard/logs/partial` — `dashboard.logs.partial` (HTMX polling)
- `GET /dashboard/stats` — `dashboard.stats.partial` (HTMX polling)
- `GET /dashboard/tokens` — `dashboard.tokens`

#### 11. Bootstrap Config
- `bootstrap/app.php` — Adicionado `redirectGuestsTo('/login')` para redirecionar não-autenticados

### Arquivos criados/modificados:
```
CRIADOS:
  resources/views/dashboard/layout.blade.php     (layout purple/gradient)
  resources/views/auth/login.blade.php           (login page standalone)
  resources/views/dashboard/logs.blade.php       (full logs page)
  resources/views/dashboard/tokens.blade.php     (tokens management page)
  resources/views/components/stream-card.blade.php  (canal card component)
  resources/views/components/token-item.blade.php   (device card component)
  app/Http/Controllers/Auth/LoginController.php  (web session auth)

MODIFICADOS:
  resources/views/dashboard/index.blade.php      (purple rebrand + components)
  resources/views/dashboard/partials/stats-cards.blade.php  (purple theme)
  resources/views/dashboard/partials/logs-table.blade.php   (purple theme + responsive)
  app/Http/Controllers/DashboardController.php   (tokens(), logsPartial(), updated stats/logs)
  routes/web.php                                 (login/logout/tokens/partials routes)
  bootstrap/app.php                              (guest redirect)
```

## 🎯 DIA 6.1 — Rebrand: ItaloStream → BaseStream (06/Mar/2026)

### Status: ✅ 59 testes passando (231 assertions), 0 falhas

### Escopo do Rebrand:
- **Nome**: ItaloStream → BaseStream (mantendo "by Italo Antonio")
- **Gradiente**: purple/pink → blue/cyan no brand text
- **Favicon**: 📺 → 📡 (blue rounded-rect SVG)
- **Meta SEO**: og:title, og:description, meta description adicionados
- **Playlist M3U**: Criado `public/playlist.m3u` com 12 canais (TV Aberta, Esportes, Notícias)
- **README.md**: Reescrito com URLs Railway, Samsung Smarters tutorial, deploy 1-click

### Arquivos modificados:
```
MODIFICADOS:
  resources/views/dashboard/layout.blade.php    (title, favicon, meta SEO, navbar brand → BaseStream blue/cyan, footer)
  resources/views/auth/login.blade.php          (title, favicon, brand → BaseStream blue/cyan, subtitle)
  routes/web.php                                (comment header, health check service name)
  app/Providers/HorizonServiceProvider.php      (gate email → italo@basestream.com.br)
  config/horizon.php                            (prefix default → basestream)
  README.md                                     (full rewrite: URLs, Samsung tutorial, deploy, structure)

CRIADOS:
  public/playlist.m3u                           (M3U playlist: 12 canais com token parameter)
```

### URLs Finais:
- Dashboard: https://basestream.railway.app/dashboard
- Login: https://basestream.railway.app/login
- M3U TV: https://basestream.railway.app/playlist.m3u?token=SEU_TOKEN
- Health: https://basestream.railway.app/health
- GitHub: github.com/italoantonio-dev/basestream

## 🎯 DIA 7 — Deploy + CI/CD Production: Zero Manual Ops (04/Mar/2026)

### Status: ✅ 59 testes passando (231 assertions), 0 falhas | Health check ✅ (DB + Redis + Queue)

### Implementações:

#### 1. GitHub Actions CI/CD (`.github/workflows/ci-cd.yml`)
- **Trigger**: push main/develop, PR to main
- **Job `test`**: ubuntu-latest, PHP 8.3, Redis 7 service container
  - Composer cache (`actions/cache@v4`), `shivammathur/setup-php@v2`
  - `cp .env.example .env.testing` + `php artisan key:generate --env=testing`
  - SQLite `:memory:` + `CACHE_STORE=array` + `QUEUE_CONNECTION=sync` para CI isolado
  - `pest --coverage --min=80` (xdebug coverage)
  - PHPStan level 8 (`continue-on-error: true` — strict para projeto novo)
  - PHP CS Fixer dry-run (`continue-on-error: true`)
- **Job `deploy`**: Railway CLI (`railway up --detach`), needs `test`, main branch only
  - Health check curl após 30s sleep
  - Discord webhook notification (optional, `secrets.DISCORD_WEBHOOK`)
- **Secrets necessários**: `RAILWAY_TOKEN`, `DISCORD_WEBHOOK` (opcional)

#### 2. PHP CS Fixer (`.php-cs-fixer.dist.php`)
- @PSR-12 base rules
- Short array syntax, ordered/no-unused imports, trailing commas in multiline
- Single quotes, operator spacing, blank lines before statements
- `setRiskyAllowed(true)`, excludes vendor/storage/bootstrap/cache/node_modules

#### 3. PHPStan Static Analysis (`phpstan.neon`)
- Level 8 (strictest), paths: `app` only
- Excludes: Console/Kernel.php, Exceptions/Handler.php
- Laravel-specific ignores: Eloquent Builder undefined methods, Model property access, RedirectResponse return type mismatches

#### 4. Railway Deploy Config (`railway.toml`)
- Builder: nixpacks (PHP 8.3)
- Start: `migrate --force && config:cache && route:cache && view:cache && php-fpm`
- Health check: `/health` endpoint, 30s timeout
- Restart: on_failure, max 3 retries

#### 5. Nginx Security Headers + Rate Limiting (`docker/nginx.conf`)
- **Rate limiting zones** (outside server block):
  - `api`: 60 req/min per IP (burst=100 nodelay)
  - `login`: 10 req/min per IP
- **Security headers** (inside server block):
  - `X-Frame-Options: SAMEORIGIN`
  - `X-XSS-Protection: 1; mode=block`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: no-referrer-when-downgrade`
  - `Content-Security-Policy`: self + unsafe-inline/eval + unpkg.com + cdn.tailwindcss.com + fonts.googleapis.com/gstatic.com (HTMX/Tailwind CDNs)
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- Rate limiting applied to `/api/` and `/api/stream` locations

#### 6. Health Check Upgrade (`routes/web.php` — `/health`)
- **Database**: `DB::select('SELECT 1')` — verifica conexão PostgreSQL
- **Redis**: `Redis::connection()->ping()` com `(bool)` cast — predis retorna Status object, não boolean
- **Queue**: `Queue::size('default')` — monitora jobs pendentes
- **Status logic**: `ok` (tudo conectado) ou `degraded` (algum serviço down) → HTTP 200 ou 503
- **Timestamp**: ISO 8601 format
- **Response exemplo**: `{"status":"ok","service":"BaseStream Proxy","version":"1.0.0","db_connected":true,"redis_connected":true,"queue_jobs":0,"timestamp":"2026-03-04T14:41:14-03:00"}`

#### 7. Bug Fix: APP_KEY inválida
- APP_KEY original (`testingkeyforbasestream12345678`) tinha 30 bytes — AES-256-CBC exige 32
- Erro: `Unsupported cipher or incorrect key length` em runtime
- Fix: Gerada nova key válida via `php -r "echo 'base64:' . base64_encode(random_bytes(32));"` + `sed -i` no .env

### Arquivos criados/modificados:
```
CRIADOS:
  .github/workflows/ci-cd.yml     (CI/CD pipeline: test + deploy)
  .php-cs-fixer.dist.php          (PSR-12 code style config)
  phpstan.neon                    (static analysis level 8)
  railway.toml                    (Railway production deploy)

MODIFICADOS:
  docker/nginx.conf               (security headers + rate limiting zones)
  routes/web.php                  (health check → DB/Redis/Queue monitoring)
  .env                            (APP_KEY corrigida: 30→32 bytes)
```

### Pipeline resumo:
```
git push main → GitHub Actions:
  ├── test (PHP 8.3 + Redis + SQLite)
  │   ├── pest --coverage --min=80
  │   ├── phpstan level 8
  │   └── php-cs-fixer dry-run
  └── deploy (needs test ✅)
      ├── railway up --detach
      ├── curl /health
      └── Discord notify 🚀
```
