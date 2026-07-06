<?php

namespace Vaslv\FilamentTopbarMenu\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves the favicon URL of an external website.
 *
 * Resolution is intentionally decoupled from rendering: it only runs from
 * the artisan command or explicit resource actions, and the result is
 * persisted to the `favicon_url` column. No HTTP request is ever made
 * while the menu is being rendered.
 */
class FaviconResolver
{
    public function resolve(string $url): ?string
    {
        if (! config('filament-topbar-menu.enable_favicons', true)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (blank($host)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($url, PHP_URL_PORT);

        $base = $scheme . '://' . $host . ($port ? ':' . $port : '');

        // 1. The conventional location.
        $faviconUrl = $base . '/favicon.ico';

        if ($this->isValidFavicon($faviconUrl)) {
            return $faviconUrl;
        }

        // 2. Fall back to parsing <link rel="icon"> tags from the page markup.
        return $this->resolveFromHtml($url);
    }

    protected function isValidFavicon(string $url): bool
    {
        $response = $this->request($url);

        if ($response === null || ! $response->successful() || $response->body() === '') {
            return false;
        }

        $contentType = strtolower($response->header('Content-Type'));

        if ($contentType === '') {
            return true;
        }

        return str_contains($contentType, 'image')
            || str_contains($contentType, 'icon')
            || str_contains($contentType, 'octet-stream');
    }

    protected function resolveFromHtml(string $pageUrl): ?string
    {
        $response = $this->request($pageUrl);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        if (! preg_match_all('/<link\b[^>]*>/i', $response->body(), $matches)) {
            return null;
        }

        foreach ($matches[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\']([^"\']*)["\']/i', $tag, $rel)) {
                continue;
            }

            if (! preg_match('/\bicon\b/i', $rel[1])) {
                continue;
            }

            if (! preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $href)) {
                continue;
            }

            $candidate = $this->makeAbsoluteUrl(html_entity_decode($href[1]), $pageUrl);

            if ($candidate === null) {
                continue;
            }

            // data: URIs are self-contained; any fetchable URL must resolve to
            // an actual image before we persist it, so a wrong href doesn't get
            // saved as a silently-broken favicon.
            if (str_starts_with($candidate, 'data:') || $this->isValidFavicon($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve a favicon href (from a <link> tag) against the page URL it was
     * found on, honoring absolute, scheme-relative, root-relative and
     * path-relative (including ../) forms. Returns null for host-less pages.
     */
    protected function makeAbsoluteUrl(string $href, string $pageUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, 'data:')) {
            return $href === '' ? null : $href;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $scheme = strtolower((string) (parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https'));
        $host = parse_url($pageUrl, PHP_URL_HOST);

        if (blank($host)) {
            return null;
        }

        // Scheme-relative: //cdn.example.com/icon.png
        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }

        $port = parse_url($pageUrl, PHP_URL_PORT);
        $authority = $scheme . '://' . $host . ($port ? ':' . $port : '');

        // Root-relative: /icon.png
        if (str_starts_with($href, '/')) {
            return $authority . $this->normalizePath($href);
        }

        // Path-relative: resolve against the page's directory.
        $path = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        $directory = substr($path, 0, strrpos($path, '/') + 1) ?: '/';

        return $authority . $this->normalizePath($directory . $href);
    }

    /**
     * Collapse `.` and `..` segments in an absolute path.
     */
    protected function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    protected function request(string $url): ?Response
    {
        try {
            return Http::timeout((int) config('filament-topbar-menu.favicon_request_timeout', 5))
                ->withHeaders(['User-Agent' => 'FilamentTopbarMenu/1.0 (favicon resolver)'])
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }
}
