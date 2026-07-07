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
 *
 * The target site is treated as untrusted. The resolver refuses to fetch
 * private / loopback / link-local addresses (SSRF), re-validates every
 * redirect hop, caps how much of the response body it reads, and only ever
 * returns a plain http(s) URL that is safe to store and render.
 */
class FaviconResolver
{
    /**
     * Maximum number of redirects to follow. Each hop's target host is
     * re-validated, so a public site cannot 302 the resolver onto an
     * internal address.
     */
    protected const MAX_REDIRECTS = 3;

    /**
     * Hard cap on how many bytes of a response body are read into memory. A
     * favicon is a few KB and only a page's <head> is scanned for <link>
     * tags, so this is ample — it stops a malicious host from OOM-ing the
     * worker with a multi-gigabyte response.
     */
    protected const MAX_BODY_BYTES = 2 * 1024 * 1024;

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

        $base = $scheme.'://'.$host.($port ? ':'.$port : '');

        // 1. The conventional location.
        $faviconUrl = $base.'/favicon.ico';

        if ($this->isStorableFaviconUrl($faviconUrl) && $this->isValidFavicon($faviconUrl)) {
            return $faviconUrl;
        }

        // 2. Fall back to parsing <link rel="icon"> tags from the page markup.
        return $this->resolveFromHtml($url);
    }

    protected function isValidFavicon(string $url): bool
    {
        $response = $this->request($url);

        if ($response === null || ! $response->successful()) {
            return false;
        }

        if ($this->limitedBody($response) === '') {
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

        if (! preg_match_all('/<link\b[^>]*>/i', $this->limitedBody($response), $matches)) {
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

            // Only ever persist a plain http(s) URL that is safe to drop into the
            // panel's CSS `url('…')` / HTML context (no quotes, angle brackets,
            // control chars; within the column limit; no `data:` URIs). Skip
            // anything else and keep looking. The candidate must also resolve to a
            // real image so a wrong href isn't saved as a silently-broken favicon.
            if ($this->isStorableFaviconUrl($candidate) && $this->isValidFavicon($candidate)) {
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
            return $scheme.':'.$href;
        }

        $port = parse_url($pageUrl, PHP_URL_PORT);
        $authority = $scheme.'://'.$host.($port ? ':'.$port : '');

        // Root-relative: /icon.png
        if (str_starts_with($href, '/')) {
            return $authority.$this->normalizePath($href);
        }

        // Path-relative: resolve against the page's directory.
        $path = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        $directory = substr($path, 0, strrpos($path, '/') + 1) ?: '/';

        return $authority.$this->normalizePath($directory.$href);
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

        return '/'.implode('/', $segments);
    }

    protected function request(string $url): ?Response
    {
        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
                $host = parse_url($url, PHP_URL_HOST);

                if (blank($host) || $this->hostIsBlocked((string) $host)) {
                    return null;
                }

                $response = Http::timeout((int) config('filament-topbar-menu.favicon_request_timeout', 5))
                    ->withHeaders(['User-Agent' => 'FilamentTopbarMenu/1.0 (favicon resolver)'])
                    // Follow redirects manually (allow_redirects off) so every hop
                    // is re-checked against the SSRF block above; stream so the
                    // body is read under a byte cap, not buffered wholesale.
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($url);

                if (! $response->redirect()) {
                    return $response;
                }

                $location = $response->header('Location');

                if (blank($location)) {
                    return $response;
                }

                $next = $this->makeAbsoluteUrl($location, $url);

                if ($next === null) {
                    return null;
                }

                $url = $next;
            }

            // Too many redirects.
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a host must not be fetched because it resolves to (or is) a
     * private, loopback, link-local or otherwise reserved address — the classic
     * SSRF targets, including the cloud metadata endpoint 169.254.169.254.
     *
     * A host that does not resolve at all is left alone: the HTTP client uses
     * the same system resolver, so it cannot reach an unresolvable host either,
     * and blocking here would only break unknown/offline hosts without adding
     * protection. Residual: a DNS-rebind between this check and the request is
     * not defended against — accepted for a favicon resolver.
     */
    protected function hostIsBlocked(string $host): bool
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->ipIsPublic($host);
        }

        $ips = $this->resolveHostIps($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->ipIsPublic($ip)) {
                return true;
            }
        }

        return false;
    }

    protected function ipIsPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Resolve a host name to every IPv4/IPv6 address it points at.
     *
     * @return list<string>
     */
    protected function resolveHostIps(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Whether a resolved favicon URL is safe to persist and later render. Only
     * plain http(s) URLs within the storage column limit, with no characters
     * that could break out of the panel's CSS `url('…')` / HTML attribute
     * context (quotes, angle brackets, whitespace, backslash). Rejects `data:`
     * URIs, which are unbounded in length and land in the same CSS sink.
     */
    protected function isStorableFaviconUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 255) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return preg_match('/[\s"\'<>\\\\]/', $url) === 0;
    }

    /**
     * Read at most MAX_BODY_BYTES from the (streamed) response body, so a
     * malicious host cannot exhaust memory with a huge response.
     */
    protected function limitedBody(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof() && strlen($body) < self::MAX_BODY_BYTES) {
            $chunk = $stream->read(self::MAX_BODY_BYTES - strlen($body));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        return $body;
    }
}
