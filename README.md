# BaseStream 📡

> IPTV Proxy dinâmico (BrazucaPlay-like) para Smarters/TV Samsung.  
> Multi-device, cache Redis, queue async. **by Italo Antonio**

## URLs

| Serviço | URL |
|---------|-----|
| Dashboard | https://basestream.railway.app/dashboard |
| M3U TV | https://basestream.railway.app/playlist.m3u?token=SEU_TOKEN |
| Health | https://basestream.railway.app/health |
| GitHub | github.com/italoantonio-dev/basestream |

## Stack

- **Backend**: Laravel 11 + PHP 8.3 (Sanctum auth, Horizon queues)
- **Database**: PostgreSQL 16
- **Cache**: Redis
- **Frontend**: HTMX + Tailwind (server-rendered, zero JS bundle)
- **Infra**: Docker Compose (dev) → Railway (prod)
- **Arquitetura**: Clean Hexagonal (Domain → Application → Infrastructure)

## Deploy 1-click

```bash
git clone https://github.com/italoantonio-dev/basestream
cd basestream
railway init
railway up
```

**Live**: https://basestream.railway.app  
**M3U**: https://basestream.railway.app/playlist.m3u?token=...

## Quick Start (Docker Local)

```bash
# 1. Clone e entre no projeto
git clone https://github.com/italoantonio-dev/basestream
cd basestream

# 2. Crie o .env
cp .env.example .env

# 3. Suba tudo
docker-compose up -d --build

# 4. Instale dependências (primeira vez)
docker-compose exec app composer install

# 5. Gere a key
docker-compose exec app php artisan key:generate

# 6. Rode migrations + seed
docker-compose exec app php artisan migrate --seed
```

O seeder cria um admin com token de teste. Acesse:

- **Dashboard**: http://localhost/dashboard  
- **Login**: http://localhost/login
- **API Health**: http://localhost/health

## 📱 Samsung TV (Smarters)

```
Smarters → M3U URL:
https://basestream.railway.app/playlist.m3u?token=SEU_TOKEN
Nome: "BaseStream HD"
→ Globo/SporTV carrega ~2s ✅
```

## API Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/api/register` | Criar conta |
| `POST` | `/api/login` | Login → Sanctum token |
| `POST` | `/api/logout` | Logout |
| `GET` | `/api/me` | Perfil do usuário |
| `GET` | `/api/tokens` | Listar tokens |
| `POST` | `/api/tokens` | Criar token de device |
| `DELETE` | `/api/tokens/{id}` | Revogar token |
| `GET` | `/api/stream?id=X&quality=HD` | **Resolve stream** |
| `GET` | `/api/streams` | Listar streams |
| `GET` | `/api/stream/proxy?url=X` | HLS proxy (CORS) |

### Exemplo: Resolver Stream

```bash
# Via token query param
curl "https://basestream.railway.app/api/stream?id=globo&quality=HD&token=bs_seu_token"

# Via Bearer header
curl -H "Authorization: Bearer bs_seu_token" \
     "https://basestream.railway.app/api/stream?id=globo"

# Redirect direto (para players)
curl -L "https://basestream.railway.app/api/stream?id=globo&token=bs_xxx&redirect=1"
```

## Estrutura do Projeto

```
app/
├── Domain/Stream/Contracts/     # Interfaces (ScraperInterface, StreamResult)
├── Application/UseCases/        # ResolveStreamUseCase, CreateApiTokenUseCase
├── Infrastructure/Scrapers/     # ScraperRegistry, BaseScraper, DemoTvScraper
├── Http/Controllers/Api/        # AuthController, TokenController, StreamController
├── Http/Controllers/Auth/       # LoginController (web session auth)
├── Http/Controllers/            # DashboardController
├── Models/                      # User, ApiToken, StreamLog, Scraper
├── Jobs/                        # ResolveStreamJob (async queue)
├── Services/                    # StreamCache (Redis abstraction)
├── Providers/                   # StreamServiceProvider, HorizonServiceProvider
config/
├── streams.php                  # Cache TTL, categorias, tiers, rate limits
├── horizon.php                  # Queue workers config
database/migrations/             # users, api_tokens, stream_logs, scrapers, indexes
resources/views/
├── dashboard/layout.blade.php   # Layout Netflix-like (HTMX + Tailwind + Alpine)
├── dashboard/index.blade.php    # Dashboard overview com stats + quick access
├── dashboard/logs.blade.php     # Logs com time-range filters
├── dashboard/tokens.blade.php   # Device/token management
├── dashboard/partials/          # HTMX-refreshable partials (stats, logs)
├── auth/login.blade.php         # Login page
├── components/                  # stream-card, token-item
├── welcome.blade.php            # Landing page
public/
├── playlist.m3u                 # M3U playlist para Smarters/VLC
docker/
├── nginx.conf                   # HLS cache proxy + CORS + gzip
├── supervisord.conf             # PHP-FPM + Horizon
├── entrypoint.sh                # Auto-setup script
```

## Adicionando Scrapers

1. Crie uma classe em `app/Infrastructure/Scrapers/`
2. Estenda `BaseScraper` e implemente `ScraperInterface`
3. Registre no `StreamServiceProvider`

```php
// app/Infrastructure/Scrapers/MeuScraper.php
class MeuScraper extends BaseScraper
{
    public function identifier(): string { return 'meu-scraper'; }
    public function category(): string { return 'tv-br'; }
    
    public function resolve(string $streamId, string $quality = 'HD'): ?StreamResult
    {
        $html = $this->fetch("https://site.com/embed/{$streamId}");
        $url = $this->extractM3u8($html);
        return $url ? $this->makeResult($url, $streamId, $quality) : null;
    }
}

// app/Providers/StreamServiceProvider.php
$registry->register(new MeuScraper());
```

## Deploy Railway

```bash
# Rebrand + deploy
git add .
git commit -m "feat: rebrand to BaseStream"
git push origin main
# Railway auto-deploy via GitHub integration
```

No Railway: New Project → GitHub Repo → Add PostgreSQL + Redis services.

## Testes

```bash
docker exec basestream-app php artisan test
# 59 tests, 231 assertions — ALL PASSING ✅
```

## Licença

Uso pessoal. Streams públicos apenas.  
Desenvolvido por **Italo Antonio** (italoantonio-dev)
