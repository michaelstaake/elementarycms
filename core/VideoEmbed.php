<?php

declare(strict_types=1);

namespace Elementary;

class VideoEmbed
{
    public static function isVideoMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * @return array{type: 'iframe'|'video', src: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            $id = self::youtubeId($url, $host, $path, $parsed['query'] ?? '');
            if ($id) {
                return ['type' => 'iframe', 'src' => 'https://www.youtube.com/embed/' . $id];
            }
        }

        if (str_contains($host, 'vimeo.com')) {
            if (preg_match('#/(\d+)#', $path, $matches)) {
                return ['type' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $matches[1]];
            }
        }

        if (preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i', $path)) {
            return ['type' => 'video', 'src' => $url];
        }

        return null;
    }

    public static function embedHtml(string $url): string
    {
        $embed = self::parse($url);
        if (!$embed) {
            return '';
        }

        if ($embed['type'] === 'iframe') {
            return '<div class="video-embed-responsive"><iframe src="' . esc($embed['src']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>';
        }

        return '<video class="page-block-video-player" controls preload="metadata" src="' . esc($embed['src']) . '"></video>';
    }

    private static function youtubeId(string $url, string $host, string $path, string $query): ?string
    {
        if (str_contains($host, 'youtu.be')) {
            $id = ltrim($path, '/');
            return $id !== '' ? $id : null;
        }

        if (preg_match('#/embed/([^/?]+)#', $path, $matches)) {
            return $matches[1];
        }

        parse_str($query, $params);
        return isset($params['v']) && $params['v'] !== '' ? (string) $params['v'] : null;
    }
}
