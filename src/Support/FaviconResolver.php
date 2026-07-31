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
     * favicon is a few KB, and a page's icon <link> tags sit in its <head>, so
     * this is ample for the markup that matters — the whole capped body is what
     * gets scanned, and the cap is what stops a malicious host from OOM-ing the
     * worker with a multi-gigabyte response.
     */
    protected const MAX_BODY_BYTES = 2 * 1024 * 1024;

    /**
     * How many `<link rel="icon">` candidates from one page are worth probing.
     * Each probe is a full HTTP request (times MAX_REDIRECTS hops, times the
     * configured timeout), and a hostile page can declare thousands of them —
     * so the page does not get to decide how long the worker is busy or how
     * many outbound requests it makes.
     */
    protected const MAX_ICON_CANDIDATES = 5;

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

        // 1. The icon the page itself declares, which is the one a browser
        //    shows in the tab for exactly this URL. A sub-page (a Horizon or
        //    Telescope dashboard, a docs section) usually overrides the site
        //    icon this way, so asking the page first is what makes the stored
        //    favicon match what the link actually leads to.
        $declared = $this->resolveFromHtml($url);

        if ($declared !== null) {
            return $declared;
        }

        // 2. Fall back to the conventional root location, for pages that
        //    declare nothing (or that the resolver cannot read — it browses
        //    anonymously, so a page behind auth answers 401/403 here).
        $faviconUrl = $base.'/favicon.ico';

        if ($this->isStorableFaviconUrl($faviconUrl) && $this->isValidFavicon($faviconUrl)) {
            return $faviconUrl;
        }

        return null;
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
        // Relative hrefs belong to the page that actually answered, not to the
        // URL asked for: `https://host/docs` → 301 → `https://host/docs/` with
        // href="favicon.png" means /docs/favicon.png, and resolving against the
        // pre-redirect URL would silently pick the site-wide icon instead.
        $finalUrl = $pageUrl;

        $response = $this->request($pageUrl, $finalUrl);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        if (! preg_match_all('/<link\b[^>]*>/i', $this->limitedBody($response), $matches)) {
            return null;
        }

        $candidates = [];

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

            $candidate = $this->makeAbsoluteUrl(html_entity_decode($href[1]), $finalUrl);

            if ($candidate === null) {
                continue;
            }

            // Only ever persist a plain http(s) URL that is safe to drop into the
            // panel's `<img src>` / CSS `url('…')` context (no quotes, angle
            // brackets, control chars; within the column limit; no `data:` URIs).
            // Skip anything else and keep looking.
            if (! $this->isStorableFaviconUrl($candidate)) {
                continue;
            }

            // Deduplicate before counting: a page repeating one href across
            // several rel variants must not eat the probe budget.
            $candidates[$candidate] = true;

            if (count($candidates) >= self::MAX_ICON_CANDIDATES) {
                break;
            }
        }

        foreach (array_keys($candidates) as $candidate) {
            // The candidate must resolve to a real image, so a wrong href isn't
            // saved as a silently-broken favicon.
            if ($this->isValidFavicon($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve a URL found in a document against the page it came from, honoring
     * absolute, scheme-relative, root-relative and path-relative (including ../)
     * forms. Returns null for host-less pages.
     *
     * Two callers, both security-relevant: favicon hrefs from a <link> tag, and
     * the `Location` header of a redirect — whose result goes straight back
     * through the SSRF check at the top of request(), so a hop must never come
     * out of here as anything but an absolute, checkable URL.
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

    /**
     * @param  string|null  $finalUrl  Set to the URL that actually answered, so
     *                                 relative hrefs in the body can be resolved
     *                                 against it instead of the requested URL.
     */
    protected function request(string $url, ?string &$finalUrl = null): ?Response
    {
        $finalUrl = $url;

        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
                $finalUrl = $url;

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
     * A host NAME that does not resolve is left alone: the HTTP client uses the
     * same system resolver, so it cannot reach an unresolvable name either, and
     * blocking here would only break unknown/offline hosts without adding
     * protection. That leniency must not extend to numeric literals, though —
     * curl accepts hex/octal/dword/short spellings of an IPv4 address that
     * filter_var() never recognizes and the system resolver recognizes only
     * sometimes (on glibc `0177.0.0.1` resolves and is caught above, while
     * `0x7f.0.0.1` resolves nowhere yet still reaches 127.0.0.1), so anything
     * that is not shaped like a host name is refused here.
     * Residual: a DNS-rebind between this check and the request is not defended
     * against — accepted for a favicon resolver.
     */
    protected function hostIsBlocked(string $host): bool
    {
        $host = trim($host, '[]');

        // An IPv6 literal, with or without a zone id (`fe80::1%25en0`). curl
        // honours the zone and connects to the link-local address, while
        // filter_var() refuses to parse the zone-qualified form at all — so
        // validate the address part alone and refuse anything unparseable
        // instead of letting it fall through to the host-name branch.
        if (str_contains($host, ':')) {
            $address = rawurldecode($host);
            $address = strstr($address, '%', true) ?: $address;

            return ! filter_var($address, FILTER_VALIDATE_IP) || ! $this->ipIsPublic($address);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->ipIsPublic($host);
        }

        $ips = $this->resolveHostIps($host);

        if ($ips === []) {
            return ! $this->looksLikeHostName($host);
        }

        foreach ($ips as $ip) {
            if (! $this->ipIsPublic($ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a host is shaped like a DNS name rather than a numeric address
     * literal. The test is the last label: a real host name ends in a letter-
     * initial TLD, while every numeric spelling curl accepts — `0x7f.0.0.1`,
     * `0177.0.0.1`, `2130706433`, `127.1` — ends in a digit-initial one.
     *
     * Unicode host names are folded to punycode first (`пример.рф` →
     * `xn--e1afmkfd.xn--p1ai`), because the system resolver does not speak IDN
     * while an IDN-capable curl does: without the fold, a perfectly real IDN
     * site would be treated as a numeric literal and refused. Same flags as
     * TopbarMenuItem's host matching, and the same graceful degradation when
     * ext-intl is missing.
     */
    protected function looksLikeHostName(string $host): bool
    {
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        $labels = explode('.', rtrim($host, '.'));

        return preg_match('/^[a-z]/i', (string) end($labels)) === 1;
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
     * that could break out of an HTML attribute context (quotes, angle brackets,
     * whitespace, backslash). Rejects `data:` URIs, which are unbounded in
     * length.
     *
     * The package renders the value through Filament's icon slot, as an escaped
     * `<img src>`; the rule stays this strict because a published view may still
     * put it in a CSS `url('…')` sink, and because a stored value that a browser
     * parses differently than PHP does is worth refusing on its own.
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
