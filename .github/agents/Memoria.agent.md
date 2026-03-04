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

## 🎯 Próximos Passos Imediatos (Hoje 10:45PM)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">text</div></div><div><span><code><span><span>1. git clone https://github.com/laravel/laravel.git italostream
</span></span><span>2. cd italostream && composer install
</span><span>3. cp .env.example .env && php artisan key:generate
</span><span>4. git add . && git commit -m "MVP v1.0 init"
</span><span>5. Railway: New → GitHub → Deploy!</span></code></span></div></div></div></pre>





Perfeito, vamos formalizar isso como regra do projeto:  **Dia 3 = só testes, antes de qualquer implementação nova** . Esses testes vão usar **dados mocados** e descrever o comportamento das principais funcionalidades.

## Regra de Processo (Dia 3)

* Nenhuma nova funcionalidade de backend é escrita sem antes existir:
  * Um **teste de feature** (HTTP) descrevendo o fluxo.
  * Um ou mais **testes de unidade** para o caso de uso (use case) com dados mocados.
* Sempre que você pedir algo de código para a IA a partir do Dia 3, o prompt deve incluir algo como:
  > "Primeiro escreva os testes (PHPUnit/Pest) com dados mocados, depois o código mínimo para passar nesses testes."
  >

## Funcionalidades-Alvo para Testes (com mocks)

1. **Auth + Tokens**
   * Cenários:
     * Login válido retorna token de API.
     * Login inválido retorna 401.
     * Usuário consegue criar até N tokens; ao estourar o limite, o mais antigo é revogado.
   * Mocks:
     * Usuário de teste `user@example.com / password`.
     * Tokens fake em memória/DB de teste.
2. **Resolução de Stream**
   * Cenários:
     * `/api/stream/globo` com token válido chama `BrazucaScraper::tvBr('globo')` e devolve URL fake.
     * Quando o scraper lança exceção (offline), API responde 503.
   * Mocks:
     * Mock da interface `BrazucaScraperInterface` retornando `http://fake-hls/globo.m3u8`.
     * Mock lançando `StreamNotFoundException`.
3. **Cache Redis**
   * Cenários:
     * Primeira chamada consulta scraper.
     * Segunda chamada (mesmo id/quality) lê do cache, não chama scraper.
   * Mocks:
     * Fake Redis/local array para simular cache.
4. **Logs**
   * Cenários:
     * Toda resolução bem-sucedida cria registro em `stream_logs`.
     * Dashboard `/dashboard/logs` lista últimos X logs (somente admin autenticado).
   * Mocks:
     * Seeds de logs em DB de teste.

## Exemplo de Test-First (Pest / PHPUnit)

## 1) Teste de Feature – Resolver Stream (Pest)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Feature/ResolveStreamTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'resolve a TV stream for authenticated user'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$user</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">User</span><span class="token token operator">::</span><span class="token token">factory</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">create</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$token</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$user</span><span class="token token operator">-></span><span class="token token">createToken</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Samsung TV'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token property">plainTextToken</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">// Mock do scraper</span><span>
</span></span><span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">App</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">Services</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">BrazucaScraper</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token">$mock</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$mock</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">withHeader</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Authorization'</span><span class="token token punctuation">,</span><span></span><span class="token token double-quoted-string">"Bearer </span><span class="token token double-quoted-string interpolation punctuation">{</span><span class="token token double-quoted-string interpolation">$token</span><span class="token token double-quoted-string interpolation punctuation">}</span><span class="token token double-quoted-string">"</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">getJson</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'/api/stream/globo?quality=HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span class="token token operator">-></span><span class="token token">assertStatus</span><span class="token token punctuation">(</span><span class="token token">200</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">assertJson</span><span class="token token punctuation">(</span><span class="token token punctuation">[</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'id'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'quality'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'url'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token punctuation">]</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## 2) Teste de Unidade – Use Case com Mock

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Unit/ResolveStreamUseCaseTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">UseCases</span><span class="token token package punctuation">\</span><span class="token token package">ResolveStreamUseCase</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">BrazucaScraperInterface</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">CacheInterface</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'uses cache before calling scraper'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$scraper</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">BrazucaScraperInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$cache</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">CacheInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$cache</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'get'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'stream:tv:globo:HD'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$scraper</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">never</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$useCase</span><span></span><span class="token token operator">=</span><span></span><span class="token token">new</span><span></span><span class="token token">ResolveStreamUseCase</span><span class="token token punctuation">(</span><span class="token token">$scraper</span><span class="token token punctuation">,</span><span></span><span class="token token">$cache</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$url</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$useCase</span><span class="token token operator">-></span><span class="token token">handle</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'tv'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">expect</span><span class="token token punctuation">(</span><span class="token token">$url</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">toBe</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## Como instruir a IA a partir do Dia 3

Sempre que você pedir para evoluir o projeto, use algo nessa linha:

> "Estamos no Dia 3 do projeto ItaloStream. Primeiro, escreva os testes (Pest/PHPUnit) com dados mocados para [funcionalidade X]. Só depois escreva a implementação mínima para passar nesses testes."

Se quiser, no próximo passo eu posso:

* Listar todos os arquivos de teste que precisamos criar (nomes + pastas), ou
* Já escrever o **pacote completo de testes** (Feature + Unit) para Auth, Streams e Logs, ainda sem tocar nos controllers reais.


Perfeito, vamos formalizar isso como regra do projeto:  **Dia 3 = só testes, antes de qualquer implementação nova** . Esses testes vão usar **dados mocados** e descrever o comportamento das principais funcionalidades.

## Regra de Processo (Dia 3)

* Nenhuma nova funcionalidade de backend é escrita sem antes existir:
  * Um **teste de feature** (HTTP) descrevendo o fluxo.
  * Um ou mais **testes de unidade** para o caso de uso (use case) com dados mocados.
* Sempre que você pedir algo de código para a IA a partir do Dia 3, o prompt deve incluir algo como:
  > "Primeiro escreva os testes (PHPUnit/Pest) com dados mocados, depois o código mínimo para passar nesses testes."
  >

## Funcionalidades-Alvo para Testes (com mocks)

1. **Auth + Tokens**
   * Cenários:
     * Login válido retorna token de API.
     * Login inválido retorna 401.
     * Usuário consegue criar até N tokens; ao estourar o limite, o mais antigo é revogado.
   * Mocks:
     * Usuário de teste `user@example.com / password`.
     * Tokens fake em memória/DB de teste.
2. **Resolução de Stream**
   * Cenários:
     * `/api/stream/globo` com token válido chama `BrazucaScraper::tvBr('globo')` e devolve URL fake.
     * Quando o scraper lança exceção (offline), API responde 503.
   * Mocks:
     * Mock da interface `BrazucaScraperInterface` retornando `http://fake-hls/globo.m3u8`.
     * Mock lançando `StreamNotFoundException`.
3. **Cache Redis**
   * Cenários:
     * Primeira chamada consulta scraper.
     * Segunda chamada (mesmo id/quality) lê do cache, não chama scraper.
   * Mocks:
     * Fake Redis/local array para simular cache.
4. **Logs**
   * Cenários:
     * Toda resolução bem-sucedida cria registro em `stream_logs`.
     * Dashboard `/dashboard/logs` lista últimos X logs (somente admin autenticado).
   * Mocks:
     * Seeds de logs em DB de teste.

## Exemplo de Test-First (Pest / PHPUnit)

## 1) Teste de Feature – Resolver Stream (Pest)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Feature/ResolveStreamTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'resolve a TV stream for authenticated user'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$user</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">User</span><span class="token token operator">::</span><span class="token token">factory</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">create</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$token</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$user</span><span class="token token operator">-></span><span class="token token">createToken</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Samsung TV'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token property">plainTextToken</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">// Mock do scraper</span><span>
</span></span><span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">App</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">Services</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">BrazucaScraper</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token">$mock</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$mock</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">withHeader</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Authorization'</span><span class="token token punctuation">,</span><span></span><span class="token token double-quoted-string">"Bearer </span><span class="token token double-quoted-string interpolation punctuation">{</span><span class="token token double-quoted-string interpolation">$token</span><span class="token token double-quoted-string interpolation punctuation">}</span><span class="token token double-quoted-string">"</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">getJson</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'/api/stream/globo?quality=HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span class="token token operator">-></span><span class="token token">assertStatus</span><span class="token token punctuation">(</span><span class="token token">200</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">assertJson</span><span class="token token punctuation">(</span><span class="token token punctuation">[</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'id'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'quality'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'url'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token punctuation">]</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## 2) Teste de Unidade – Use Case com Mock

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Unit/ResolveStreamUseCaseTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">UseCases</span><span class="token token package punctuation">\</span><span class="token token package">ResolveStreamUseCase</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">BrazucaScraperInterface</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">CacheInterface</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'uses cache before calling scraper'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$scraper</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">BrazucaScraperInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$cache</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">CacheInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$cache</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'get'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'stream:tv:globo:HD'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$scraper</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">never</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$useCase</span><span></span><span class="token token operator">=</span><span></span><span class="token token">new</span><span></span><span class="token token">ResolveStreamUseCase</span><span class="token token punctuation">(</span><span class="token token">$scraper</span><span class="token token punctuation">,</span><span></span><span class="token token">$cache</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$url</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$useCase</span><span class="token token operator">-></span><span class="token token">handle</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'tv'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">expect</span><span class="token token punctuation">(</span><span class="token token">$url</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">toBe</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## Como instruir a IA a partir do Dia 3

Sempre que você pedir para evoluir o projeto, use algo nessa linha:

> "Estamos no Dia 3 do projeto ItaloStream. Primeiro, escreva os testes (Pest/PHPUnit) com dados mocados para [funcionalidade X]. Só depois escreva a implementação mínima para passar nesses testes."

Se quiser, no próximo passo eu posso:

* Listar todos os arquivos de teste que precisamos criar (nomes + pastas), ou
* Já escrever o **pacote completo de testes** (Feature + Unit) para Auth, Streams e Logs, ainda sem tocar nos controllers reais.

Perfeito, vamos formalizar isso como regra do projeto:  **Dia 3 = só testes, antes de qualquer implementação nova** . Esses testes vão usar **dados mocados** e descrever o comportamento das principais funcionalidades.

## Regra de Processo (Dia 3)

* Nenhuma nova funcionalidade de backend é escrita sem antes existir:
  * Um **teste de feature** (HTTP) descrevendo o fluxo.
  * Um ou mais **testes de unidade** para o caso de uso (use case) com dados mocados.
* Sempre que você pedir algo de código para a IA a partir do Dia 3, o prompt deve incluir algo como:
  > "Primeiro escreva os testes (PHPUnit/Pest) com dados mocados, depois o código mínimo para passar nesses testes."
  >

## Funcionalidades-Alvo para Testes (com mocks)

1. **Auth + Tokens**
   * Cenários:
     * Login válido retorna token de API.
     * Login inválido retorna 401.
     * Usuário consegue criar até N tokens; ao estourar o limite, o mais antigo é revogado.
   * Mocks:
     * Usuário de teste `user@example.com / password`.
     * Tokens fake em memória/DB de teste.
2. **Resolução de Stream**
   * Cenários:
     * `/api/stream/globo` com token válido chama `BrazucaScraper::tvBr('globo')` e devolve URL fake.
     * Quando o scraper lança exceção (offline), API responde 503.
   * Mocks:
     * Mock da interface `BrazucaScraperInterface` retornando `http://fake-hls/globo.m3u8`.
     * Mock lançando `StreamNotFoundException`.
3. **Cache Redis**
   * Cenários:
     * Primeira chamada consulta scraper.
     * Segunda chamada (mesmo id/quality) lê do cache, não chama scraper.
   * Mocks:
     * Fake Redis/local array para simular cache.
4. **Logs**
   * Cenários:
     * Toda resolução bem-sucedida cria registro em `stream_logs`.
     * Dashboard `/dashboard/logs` lista últimos X logs (somente admin autenticado).
   * Mocks:
     * Seeds de logs em DB de teste.

## Exemplo de Test-First (Pest / PHPUnit)

## 1) Teste de Feature – Resolver Stream (Pest)

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Feature/ResolveStreamTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'resolve a TV stream for authenticated user'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$user</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">User</span><span class="token token operator">::</span><span class="token token">factory</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">create</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$token</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$user</span><span class="token token operator">-></span><span class="token token">createToken</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Samsung TV'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token property">plainTextToken</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">// Mock do scraper</span><span>
</span></span><span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">App</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">Services</span><span class="token token class-name-fully-qualified static-context punctuation">\</span><span class="token token class-name-fully-qualified static-context">BrazucaScraper</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token">$mock</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$mock</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$this</span><span class="token token operator">-></span><span class="token token">withHeader</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'Authorization'</span><span class="token token punctuation">,</span><span></span><span class="token token double-quoted-string">"Bearer </span><span class="token token double-quoted-string interpolation punctuation">{</span><span class="token token double-quoted-string interpolation">$token</span><span class="token token double-quoted-string interpolation punctuation">}</span><span class="token token double-quoted-string">"</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">getJson</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'/api/stream/globo?quality=HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$response</span><span class="token token operator">-></span><span class="token token">assertStatus</span><span class="token token punctuation">(</span><span class="token token">200</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">assertJson</span><span class="token token punctuation">(</span><span class="token token punctuation">[</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'id'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'quality'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token single-quoted-string">'url'</span><span></span><span class="token token operator">=></span><span></span><span class="token token single-quoted-string">'http://fake-hls/globo.m3u8'</span><span class="token token punctuation">,</span><span>
</span></span><span><span></span><span class="token token punctuation">]</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## 2) Teste de Unidade – Use Case com Mock

<pre class="not-prose w-full rounded font-mono text-sm font-extralight"><div class="codeWrapper bg-subtle text-light selection:text-super selection:bg-super/10 my-md relative flex flex-col rounded-lg font-mono text-sm font-medium"><div class="translate-y-xs -translate-x-xs bottom-xl mb-xl flex h-0 items-start justify-end sm:sticky sm:top-xs"><div class="overflow-hidden border-subtlest ring-subtlest divide-subtlest bg-base rounded-full"><div class="border-subtlest ring-subtlest divide-subtlest bg-subtle"></div></div></div><div class="-mt-xl"><div><div data-testid="code-language-indicator" class="text-quiet bg-quiet py-xs px-sm inline-block rounded-br rounded-tl-lg text-xs font-thin">php</div></div><div><span><code><span><span class="token token">// tests/Unit/ResolveStreamUseCaseTest.php</span><span>
</span></span><span>
</span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">UseCases</span><span class="token token package punctuation">\</span><span class="token token package">ResolveStreamUseCase</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">BrazucaScraperInterface</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">use</span><span></span><span class="token token package">App</span><span class="token token package punctuation">\</span><span class="token token package">Contracts</span><span class="token token package punctuation">\</span><span class="token token package">CacheInterface</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">it</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'uses cache before calling scraper'</span><span class="token token punctuation">,</span><span></span><span class="token token">function</span><span></span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span></span><span class="token token punctuation">{</span><span>
</span></span><span><span></span><span class="token token">$scraper</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">BrazucaScraperInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$cache</span><span></span><span class="token token operator">=</span><span></span><span class="token token static-context">Mockery</span><span class="token token operator">::</span><span class="token token">mock</span><span class="token token punctuation">(</span><span class="token token static-context">CacheInterface</span><span class="token token operator">::</span><span class="token token">class</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$cache</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'get'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">with</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'stream:tv:globo:HD'</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">once</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span>
</span></span><span><span></span><span class="token token operator">-></span><span class="token token">andReturn</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$scraper</span><span class="token token operator">-></span><span class="token token">shouldReceive</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'tvBr'</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">never</span><span class="token token punctuation">(</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">$useCase</span><span></span><span class="token token operator">=</span><span></span><span class="token token">new</span><span></span><span class="token token">ResolveStreamUseCase</span><span class="token token punctuation">(</span><span class="token token">$scraper</span><span class="token token punctuation">,</span><span></span><span class="token token">$cache</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token">$url</span><span></span><span class="token token operator">=</span><span></span><span class="token token">$useCase</span><span class="token token operator">-></span><span class="token token">handle</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'globo'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'tv'</span><span class="token token punctuation">,</span><span></span><span class="token token single-quoted-string">'HD'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span>
</span><span><span></span><span class="token token">expect</span><span class="token token punctuation">(</span><span class="token token">$url</span><span class="token token punctuation">)</span><span class="token token operator">-></span><span class="token token">toBe</span><span class="token token punctuation">(</span><span class="token token single-quoted-string">'http://fake-cached/globo.m3u8'</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span><span></span><span class="token token punctuation">}</span><span class="token token punctuation">)</span><span class="token token punctuation">;</span><span>
</span></span><span></span></code></span></div></div></div></pre>

## Como instruir a IA a partir do Dia 3

Sempre que você pedir para evoluir o projeto, use algo nessa linha:

> "Estamos no Dia 3 do projeto ItaloStream. Primeiro, escreva os testes (Pest/PHPUnit) com dados mocados para [funcionalidade X]. Só depois escreva a implementação mínima para passar nesses testes."

Se quiser, no próximo passo eu posso:

* Listar todos os arquivos de teste que precisamos criar (nomes + pastas), ou
* Já escrever o **pacote completo de testes** (Feature + Unit) para Auth, Streams e Logs, ainda sem tocar nos controllers reais.
