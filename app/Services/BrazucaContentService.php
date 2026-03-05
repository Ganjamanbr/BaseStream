<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço que busca e parseia conteúdo dos mesmos XMLs do BrazucaPlay.
 * Fontes: GitHub Gist do skyrisk (brazucaplay).
 */
class BrazucaContentService
{
    /** Base URL para Gists raw */
    private const GIST_BASE = 'https://gist.githubusercontent.com/skyrisk';

    /** Gist IDs por tipo de conteúdo */
    private const GISTS = [
        'channels'   => '16070347f20c87c72540f9f805b57a66/raw/channels.xml',
        'series'     => '16070347f20c87c72540f9f805b57a66/raw/SeriesBase',
        'animes'     => '16070347f20c87c72540f9f805b57a66/raw/AnimesBase',
        'doramas'    => '16070347f20c87c72540f9f805b57a66/raw/DoramasBase',
        'desenhos'   => '16070347f20c87c72540f9f805b57a66/raw/DesenhosBase',
        'novelas'    => '07f1f4cd1b203cbf2efec959c4e8645a/raw/novelas.xml',
        'movies'     => '5b87797329c7b46422565ffbaab3be7e/raw/page.xml',
        'trilogias'  => '5b87797329c7b46422565ffbaab3be7e/raw/trilogias_list.xml',
        'lancamentos' => '5b87797329c7b46422565ffbaab3be7e/raw/lancamentos.xml',
    ];

    /** Gêneros de filmes disponíveis */
    private const MOVIE_GENRES = [
        'animacao', 'aventura', 'acao', 'cinematv', 'comedia', 'crime',
        'documentario', 'drama', 'familia', 'fantasia', 'faroeste',
        'ficcao_cientifica', 'guerra', 'historia', 'misterio', 'musica',
        'romance', 'suspense', 'terror', 'thriller',
    ];

    private const GENRE_LABELS = [
        'animacao' => 'Animação', 'aventura' => 'Aventura', 'acao' => 'Ação',
        'cinematv' => 'Cinema TV', 'comedia' => 'Comédia', 'crime' => 'Crime',
        'documentario' => 'Documentário', 'drama' => 'Drama', 'familia' => 'Família',
        'fantasia' => 'Fantasia', 'faroeste' => 'Faroeste',
        'ficcao_cientifica' => 'Ficção Científica', 'guerra' => 'Guerra',
        'historia' => 'História', 'misterio' => 'Mistério', 'musica' => 'Música',
        'romance' => 'Romance', 'suspense' => 'Suspense', 'terror' => 'Terror',
        'thriller' => 'Thriller',
    ];

    /** Logos base URL */
    private const LOGOS_BASE = 'https://skyrisk.github.io/brazucaplay/logos/';

    /** Categorias de TV ao vivo */
    private const TV_CATEGORIES = [
        'canais_abertos' => 'Canais Abertos',
        'documentarios' => 'Documentários',
        'esportes' => 'Esportes',
        'filmes_series' => 'Filmes & Séries',
        'infantil' => 'Infantil',
        'musicas_variedades' => 'Músicas & Variedades',
        'noticias' => 'Notícias',
        'reality_shows' => 'Reality Shows',
    ];

    /** Cache TTL em segundos */
    private int $cacheTtl = 1800; // 30 min

    /**
     * Busca XML raw de um Gist.
     */
    private function fetchGist(string $key): ?string
    {
        $path = self::GISTS[$key] ?? null;
        if (!$path) {
            return null;
        }

        $url = self::GIST_BASE . '/' . $path;

        return Cache::remember("brazuca_gist_{$key}", $this->cacheTtl, function () use ($url, $key) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => '*/*',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }

                Log::warning("BrazucaContent: Failed to fetch {$key}: HTTP {$response->status()}");
                return null;
            } catch (\Throwable $e) {
                Log::error("BrazucaContent: Exception fetching {$key}: {$e->getMessage()}");
                return null;
            }
        });
    }

    /**
     * Parseia XML no formato <channel> do BrazucaPlay.
     * Retorna array de itens com name, thumbnail, fanart, info, link.
     */
    private function parseChannelsXml(string $xml): array
    {
        $items = [];

        // Parse <channel> blocks
        preg_match_all('/<channel>(.*?)<\/channel>/s', $xml, $channelMatches);

        foreach ($channelMatches[1] as $block) {
            $item = [];

            // Extrair campos
            preg_match('/<name>(.*?)<\/name>/s', $block, $m);
            $item['name'] = $this->cleanKodiTags($m[1] ?? '');

            preg_match('/<thumbnail>(.*?)<\/thumbnail>/s', $block, $m);
            $thumb = trim($m[1] ?? '');
            // Pode ser base64 ou URL direta
            if ($thumb && !str_starts_with($thumb, 'http')) {
                $decoded = @base64_decode($thumb);
                $item['thumbnail'] = $decoded && str_starts_with($decoded, 'http') ? $decoded : '';
            } else {
                $item['thumbnail'] = $thumb;
            }

            preg_match('/<fanart>(.*?)<\/fanart>/s', $block, $m);
            $item['fanart'] = trim($m[1] ?? '');

            preg_match('/<info>(.*?)<\/info>/s', $block, $m);
            $item['info'] = trim($m[1] ?? '');

            preg_match('/<externallink>(.*?)<\/externallink>/s', $block, $m);
            $item['link'] = trim($m[1] ?? '');

            if (!empty($item['name'])) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parseia XML no formato <item> do BrazucaPlay (TV ao vivo, filmes).
     */
    private function parseItemsXml(string $xml): array
    {
        $items = [];

        preg_match_all('/<item>(.*?)<\/item>/s', $xml, $itemMatches);

        foreach ($itemMatches[1] as $block) {
            $item = [];

            preg_match('/<title>(.*?)<\/title>/s', $block, $m);
            $item['name'] = $this->cleanKodiTags($m[1] ?? '');

            preg_match('/<link>(.*?)<\/link>/s', $block, $m);
            $item['link'] = trim($m[1] ?? '');

            preg_match('/<thumbnail>(.*?)<\/thumbnail>/s', $block, $m);
            $thumb = trim($m[1] ?? '');
            if ($thumb && !str_starts_with($thumb, 'http')) {
                $decoded = @base64_decode($thumb);
                $item['thumbnail'] = $decoded && str_starts_with($decoded, 'http') ? $decoded : '';
            } else {
                $item['thumbnail'] = $thumb;
            }

            preg_match('/<epgid>(.*?)<\/epgid>/s', $block, $m);
            $item['epg_id'] = trim($m[1] ?? '');

            preg_match('/<fanart>(.*?)<\/fanart>/s', $block, $m);
            $item['fanart'] = trim($m[1] ?? '');

            if (!empty($item['name'])) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Remove Kodi formatting tags [B], [COLOR xxx], etc.
     */
    private function cleanKodiTags(string $text): string
    {
        $text = preg_replace('/\[B\]|\[\/B\]|\[I\]|\[\/I\]/i', '', $text);
        $text = preg_replace('/\[COLOR\s+\w+\]/i', '', $text);
        $text = preg_replace('/\[\/COLOR\]/i', '', $text);
        return trim($text);
    }

    // ========================================================================
    // PUBLIC API
    // ========================================================================

    /**
     * Retorna as categorias principais do menu.
     */
    public function getCategories(): array
    {
        return [
            [
                'id' => 'tv',
                'name' => 'TV AO VIVO',
                'icon' => '📺',
                'description' => 'Canais brasileiros ao vivo',
                'route' => route('content.tv'),
            ],
            [
                'id' => 'filmes',
                'name' => 'FILMES',
                'icon' => '🎬',
                'description' => 'Filmes por gênero',
                'route' => route('content.filmes'),
            ],
            [
                'id' => 'series',
                'name' => 'SÉRIES',
                'icon' => '📺',
                'description' => 'Séries e temporadas',
                'route' => route('content.series'),
            ],
            [
                'id' => 'animes',
                'name' => 'ANIMES',
                'icon' => '🎌',
                'description' => 'Animes legendados e dublados',
                'route' => route('content.animes'),
            ],
            [
                'id' => 'novelas',
                'name' => 'NOVELAS',
                'icon' => '💃',
                'description' => 'Novelas brasileiras',
                'route' => route('content.novelas'),
            ],
            [
                'id' => 'desenhos',
                'name' => 'DESENHOS',
                'icon' => '🧸',
                'description' => 'Desenhos e cartoons',
                'route' => route('content.desenhos'),
            ],
            [
                'id' => 'doramas',
                'name' => 'DORAMAS',
                'icon' => '🇰🇷',
                'description' => 'Doramas coreanos',
                'route' => route('content.doramas'),
            ],
            [
                'id' => 'pluto',
                'name' => 'PLUTO TV',
                'icon' => '🆓',
                'description' => 'Canais gratuitos',
                'route' => route('content.pluto'),
            ],
        ];
    }

    /**
     * Retorna canais de TV ao vivo, categorizados.
     */
    public function getLiveChannels(): array
    {
        $xml = $this->fetchGist('channels');
        if (!$xml) {
            return [];
        }

        // O channels.xml contém <channel> blocks organizados
        // com sub-categorias dentro de expandedlink/externallink
        $allItems = $this->parseChannelsXml($xml);

        // Filtra apenas links diretos de canal (chresolver1=xxx)
        // e links de sub-listas (ex: #channel_1, category=xxx)
        return $allItems;
    }

    /**
     * Retorna sub-XML dado um link interno (ex: gist sub-pages).
     */
    public function getSubContent(string $link): array
    {
        // Links internos começam com # — são categorias locais
        if (str_starts_with($link, '#')) {
            return $this->resolveInternalLink($link);
        }

        // Links que apontam para outros XMLs no Gist
        if (str_starts_with($link, 'http')) {
            $xml = Cache::remember('brazuca_sub_' . md5($link), $this->cacheTtl, function () use ($link) {
                try {
                    $response = Http::timeout(15)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                        ->get($link);
                    return $response->successful() ? $response->body() : null;
                } catch (\Throwable) {
                    return null;
                }
            });

            if (!$xml) return [];

            // Tenta parsear como channels ou items
            $channels = $this->parseChannelsXml($xml);
            if (!empty($channels)) return $channels;

            return $this->parseItemsXml($xml);
        }

        return [];
    }

    /**
     * Resolve link interno (# prefixed).
     */
    private function resolveInternalLink(string $link): array
    {
        // Links como #series_list=xxx contêm múltiplos sources separados por |
        if (str_contains($link, '=')) {
            $parts = explode('=', $link, 2);
            $sources = explode('|', $parts[1]);

            return array_map(function ($source, $idx) {
                return [
                    'name' => "Fonte " . ($idx + 1),
                    'link' => $source,
                    'thumbnail' => '',
                    'info' => '',
                ];
            }, $sources, array_keys($sources));
        }

        return [];
    }

    /**
     * Retorna lista de séries.
     */
    public function getSeries(): array
    {
        $xml = $this->fetchGist('series');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna lista de animes.
     */
    public function getAnimes(): array
    {
        $xml = $this->fetchGist('animes');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna lista de doramas.
     */
    public function getDoramas(): array
    {
        $xml = $this->fetchGist('doramas');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna lista de desenhos.
     */
    public function getDesenhos(): array
    {
        $xml = $this->fetchGist('desenhos');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna lista de novelas.
     */
    public function getNovelas(): array
    {
        $xml = $this->fetchGist('novelas');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna gêneros de filmes.
     */
    public function getMovieGenres(): array
    {
        $icons = [
            'animacao' => '🎨', 'aventura' => '🏔️', 'acao' => '💥', 'cinematv' => '📺',
            'comedia' => '😂', 'crime' => '🔫', 'documentario' => '📹', 'drama' => '🎭',
            'familia' => '👨‍👩‍👧‍👦', 'fantasia' => '🧙', 'faroeste' => '🤠',
            'ficcao_cientifica' => '🚀', 'guerra' => '⚔️', 'historia' => '📜',
            'misterio' => '🔍', 'musica' => '🎵', 'romance' => '❤️',
            'suspense' => '😰', 'terror' => '👻', 'thriller' => '🔪',
        ];

        return array_map(function ($slug) use ($icons) {
            return [
                'slug' => $slug,
                'name' => self::GENRE_LABELS[$slug] ?? ucfirst($slug),
                'icon' => $icons[$slug] ?? '🎬',
            ];
        }, self::MOVIE_GENRES);
    }

    /**
     * Retorna filmes por gênero.
     */
    public function getMoviesByGenre(string $genre): array
    {
        $url = self::GIST_BASE . '/5b87797329c7b46422565ffbaab3be7e/raw/' . $genre . '.xml';

        $xml = Cache::remember("brazuca_movies_{$genre}", $this->cacheTtl, function () use ($url) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get($url);
                return $response->successful() ? $response->body() : null;
            } catch (\Throwable) {
                return null;
            }
        });

        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna lançamentos de filmes.
     */
    public function getMovieLancamentos(): array
    {
        $xml = $this->fetchGist('lancamentos');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Retorna filmes no catálogo principal.
     */
    public function getMovies(): array
    {
        $xml = $this->fetchGist('movies');
        if (!$xml) return [];
        return $this->parseChannelsXml($xml);
    }

    /**
     * Busca conteúdo por nome em todas as categorias.
     */
    public function search(string $query): array
    {
        $query = mb_strtolower($query);
        $results = [];

        // Busca em cada categoria
        $categories = [
            'series'   => ['method' => 'getSeries', 'type' => 'Série'],
            'filmes'   => ['method' => 'getMovies', 'type' => 'Filme'],
            'animes'   => ['method' => 'getAnimes', 'type' => 'Anime'],
            'novelas'  => ['method' => 'getNovelas', 'type' => 'Novela'],
            'desenhos' => ['method' => 'getDesenhos', 'type' => 'Desenho'],
            'doramas'  => ['method' => 'getDoramas', 'type' => 'Dorama'],
        ];

        foreach ($categories as $catId => $config) {
            $items = $this->{$config['method']}();
            foreach ($items as $item) {
                if (str_contains(mb_strtolower($item['name']), $query)) {
                    $item['category'] = $catId;
                    $item['type'] = $config['type'];
                    $results[] = $item;
                }
            }
        }

        return $results;
    }

    /**
     * Resolve os sources de um link de conteúdo.
     * Links podem ser:
     * - serie3=slug|wvmob=base64|resolver1_tvshows=slug (múltiplos sources)
     * - chresolver1=channel_slug (canal TV)
     * - URL direta http://...m3u8
     */
    public function resolveContentSources(string $link): array
    {
        $sources = [];

        if (str_contains($link, '|')) {
            // Múltiplos sources separados por |
            $parts = explode('|', $link);
            foreach ($parts as $idx => $part) {
                $sources[] = $this->parseSourceLink(trim($part), $idx + 1);
            }
        } else {
            $sources[] = $this->parseSourceLink(trim($link), 1);
        }

        return array_filter($sources);
    }

    /**
     * Parseia um link source individual.
     */
    private function parseSourceLink(string $link, int $index): array
    {
        // chresolver1=channel_slug → Canal IPTV
        if (str_starts_with($link, 'chresolver1=')) {
            $slug = substr($link, strlen('chresolver1='));
            return [
                'type' => 'iptv',
                'label' => "IPTV (Opção {$index})",
                'resolver' => 'chresolver1',
                'slug' => $slug,
                'url' => $link,
                'host' => 'IPTV',
                'link' => $link,
            ];
        }

        // resolver1_tvshows=slug
        if (preg_match('/^resolver(\d+)_tvshows=(.+)$/', $link, $m)) {
            return [
                'type' => 'resolver',
                'label' => "Servidor {$m[1]} (Opção {$index})",
                'resolver' => "resolver{$m[1]}",
                'slug' => $m[2],
                'url' => $link,
                'host' => "API Resolver {$m[1]}",
                'link' => $link,
            ];
        }

        // serie3=slug
        if (str_starts_with($link, 'serie3=')) {
            return [
                'type' => 'resolver',
                'label' => "Servidor 3 (Opção {$index})",
                'resolver' => 'resolver3',
                'slug' => substr($link, 7),
                'url' => $link,
                'host' => 'API Resolver 3',
                'link' => $link,
            ];
        }

        // wvmob=base64 ou wovy=
        if (str_starts_with($link, 'wvmob=') || str_starts_with($link, 'wovy=')) {
            return [
                'type' => 'wvmob',
                'label' => "WOVY (Opção {$index})",
                'resolver' => 'wvmob',
                'slug' => explode('=', $link, 2)[1],
                'url' => $link,
                'host' => 'WOVY',
                'link' => $link,
            ];
        }

        // overflix=
        if (str_starts_with($link, 'overflix=')) {
            return [
                'type' => 'overflix',
                'label' => "Overflix (Opção {$index})",
                'resolver' => 'overflix',
                'slug' => substr($link, 8),
                'url' => $link,
                'host' => 'Overflix',
                'link' => $link,
            ];
        }

        // bunnycdn=
        if (str_starts_with($link, 'bunnycdn=')) {
            return [
                'type' => 'bunnycdn',
                'label' => "BunnyCDN (Opção {$index})",
                'resolver' => 'bunnycdn',
                'slug' => substr($link, 9),
                'url' => $link,
                'host' => 'BunnyCDN',
                'link' => $link,
            ];
        }

        // doramas_online=
        if (str_starts_with($link, 'doramas_online=')) {
            return [
                'type' => 'doramas',
                'label' => "DoramasOnline (Opção {$index})",
                'resolver' => 'doramas',
                'slug' => substr($link, 15),
                'url' => $link,
                'host' => 'DoramasOnline',
                'link' => $link,
            ];
        }

        // URL direta (http:// ou https://)
        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return [
                'type' => 'direct',
                'label' => "Link Direto (Opção {$index})",
                'resolver' => 'direct',
                'slug' => '',
                'url' => $link,
                'link' => $link,
            ];
        }

        // Outros links → tentar interpretar como URL
        return [
            'type' => 'unknown',
            'label' => "Opção {$index}",
            'resolver' => 'unknown',
            'slug' => $link,
            'url' => $link,
            'host' => 'Desconhecido',
            'link' => $link,
        ];
    }

    /**
     * Retorna categorias de TV ao vivo.
     */
    public function getTvCategories(): array
    {
        return self::TV_CATEGORIES;
    }

    /**
     * Retorna URL do logo de um canal.
     */
    public static function getLogoUrl(string $channelSlug): string
    {
        return self::LOGOS_BASE . strtolower($channelSlug) . '.png';
    }
}
