<?php

namespace Vaslv\FilamentTopbarMenu\Support;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

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

    /**
     * Fallback for `favicon_resolution_timeout` — see the config file.
     */
    protected const DEFAULT_RESOLUTION_TIMEOUT = 20;

    /**
     * The conventional icon file names, tried in this order inside each
     * candidate directory.
     *
     * `favicon.ico` is the only one a browser guesses at, but it is no longer
     * the only one sites ship: a modern site commonly serves `favicon.svg`
     * alone and declares it from the markup — which leaves nothing at all for a
     * resolver that can't read that markup (a page behind auth, a shell whose
     * icon is declared from JavaScript) and only ever asks for the `.ico`.
     *
     * The order is what a browser would settle on when a host has several. The
     * list stays short on purpose: every name is one more outbound request per
     * candidate directory on a host that has no icon at all, and
     * `apple-touch-icon.png` — the obvious fourth — is left out because a site
     * that ships one virtually always declares it in the markup, where step 1
     * already picks it up.
     *
     * @var list<string>
     */
    protected const CONVENTIONAL_FAVICON_NAMES = [
        'favicon.ico',
        'favicon.svg',
        'favicon.png',
    ];

    /**
     * When the search running right now must stop, as a `microtime(true)`
     * value, or null outside a search.
     *
     * Instance state rather than a parameter threaded through five methods:
     * locate() is the only entry point and the search is not reentrant, so the
     * deadline is set on the way in and cleared on the way out.
     */
    protected ?float $deadline = null;

    /**
     * The URL of the site's favicon, to be stored as a link. The visitor's own
     * browser is what fetches it later, so this only works for icons every
     * panel user can reach — see resolveEmbedded() for the ones they cannot.
     */
    public function resolve(string $url): ?string
    {
        return $this->locate($url)['url'] ?? null;
    }

    /**
     * The site's favicon as a self-contained `data:` URI, bytes and all.
     *
     * This is the form to store when the icon is not reachable from every
     * visitor's browser: a host behind HTTP auth, an internal-only service, a
     * site that blocks hotlinking. A stored link would render as a broken image
     * (or, worse, prompt each visitor for the site's credentials); the embedded
     * copy renders for everyone and makes the panel stop reaching out to
     * third-party hosts on every page load.
     *
     * The same page-declared / conventional-location search resolve() runs, and
     * the same SSRF guard, so this cannot reach anything resolve() would refuse.
     *
     * Where it differs: a candidate that cannot be embedded does not end the
     * search. "Too big to store" and "not a format we render" are not the same
     * answer as "this site has no icon" — a page whose first declared icon is a
     * 180×180 apple-touch-icon usually declares the real one right below it.
     * Returns null only when nothing on the page can be embedded.
     */
    public function resolveEmbedded(string $url): ?string
    {
        $data = null;

        $this->locate($url, function (string $body) use (&$data): bool {
            $data = TopbarMenuItem::faviconDataFromBytes($body);

            return $data !== null;
        });

        return $data;
    }

    /**
     * Find the site's favicon: its URL and the bytes that answered there.
     *
     * The bytes come back with the URL because the search has already read them
     * — a candidate only counts once it has been fetched and confirmed to be an
     * image. Returning them lets resolveEmbedded() store the icon without
     * downloading it a second time.
     *
     * @param  Closure(string): bool|null  $accept  Extra test a fetched candidate
     *                                              must pass. When it fails, the
     *                                              search moves on to the next
     *                                              candidate instead of stopping.
     * @return array{url: string, body: string}|null
     */
    protected function locate(string $url, ?Closure $accept = null): ?array
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

        $this->deadline = microtime(true) + $this->resolutionTimeout();

        try {
            // 1. The icon the page itself declares, which is the one a browser
            //    shows in the tab for exactly this URL. A sub-page (a Horizon or
            //    Telescope dashboard, a docs section) usually overrides the site
            //    icon this way, so asking the page first is what makes the stored
            //    favicon match what the link actually leads to.
            $declared = $this->resolveFromHtml($url, $accept);

            if ($declared !== null) {
                return $declared;
            }

            // 2. Fall back to the conventional locations, for pages that declare
            //    nothing (or that the resolver cannot read — it browses
            //    anonymously, so a page behind auth answers 401/403 here). The
            //    directory the link points at is tried before the host root, so an
            //    app mounted on a sub-path keeps its own icon, and each is tried in
            //    every conventional format, not just `.ico`.
            foreach ($this->conventionalFaviconUrls($base, (string) (parse_url($url, PHP_URL_PATH) ?: '/')) as $faviconUrl) {
                if ($this->isOutOfTime()) {
                    break;
                }

                if (! $this->isStorableFaviconUrl($faviconUrl)) {
                    continue;
                }

                $body = $this->fetchFavicon($faviconUrl);

                if ($body !== null && ($accept === null || $accept($body))) {
                    return ['url' => $faviconUrl, 'body' => $body];
                }
            }

            return null;
        } finally {
            $this->deadline = null;
        }
    }

    /**
     * The wall-clock budget for resolving one item, in seconds. A value that is
     * not a positive number falls back to the default rather than removing the
     * bound, which is the one thing a misconfigured cap must not do.
     */
    protected function resolutionTimeout(): float
    {
        $configured = config('filament-topbar-menu.favicon_resolution_timeout', self::DEFAULT_RESOLUTION_TIMEOUT);

        if (! is_numeric($configured) || (float) $configured <= 0) {
            return (float) self::DEFAULT_RESOLUTION_TIMEOUT;
        }

        return (float) $configured;
    }

    /**
     * Whether the search running right now has spent its budget.
     */
    protected function isOutOfTime(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }

    /**
     * The conventional icon locations for a link: every name in
     * CONVENTIONAL_FAVICON_NAMES under the directory the link points at, then
     * the same names under the host root.
     *
     * A browser only ever asks for the root `favicon.ico` — it learns about
     * anything else from the markup. That is not enough here: a single-page app
     * declares its icon from JavaScript, so the server-rendered shell this
     * resolver reads has no `<link>` at all, and the root of such a host is
     * often an API that 404s. A Directus panel on `/admin` is the shape of it:
     * nothing in the HTML, no root icon, and the real one sitting at
     * `/admin/favicon.ico`.
     *
     * Directory before name, so a sub-path app's own icon still beats the
     * site-wide one whatever format either is in.
     *
     * The last segment is treated as a file when it contains a dot, so
     * `/docs/page.html` probes `/docs/`, not `/docs/page.html/`. When the two
     * directories coincide (a link to the host root), each name is tried once.
     *
     * @return list<string>
     */
    protected function conventionalFaviconUrls(string $base, string $path): array
    {
        $directory = $path;

        if (! str_ends_with($directory, '/')) {
            $lastSegment = substr($directory, strrpos($directory, '/') + 1);

            $directory = str_contains($lastSegment, '.')
                ? substr($directory, 0, strrpos($directory, '/') + 1)
                : $directory.'/';
        }

        $urls = [];

        foreach ([$this->normalizePath($directory), '/'] as $candidateDirectory) {
            foreach (self::CONVENTIONAL_FAVICON_NAMES as $name) {
                $urls[] = $base.$candidateDirectory.$name;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Fetch a favicon candidate and return its bytes, or null when the URL does
     * not answer with something that can be an icon.
     *
     * A missing `Content-Type` is accepted — plenty of static hosts omit it for
     * `.ico` — but an HTML error page served with `200` is not, which is what
     * the type check is really for: a single-page app answers its own 404 route
     * with the app shell, and storing that as an icon would be a broken image on
     * every panel page.
     */
    protected function fetchFavicon(string $url): ?string
    {
        $response = $this->request($url);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $body = $this->limitedBody($response);

        if ($body === '') {
            return null;
        }

        $contentType = strtolower($response->header('Content-Type'));

        if ($contentType === '') {
            return $body;
        }

        $isImage = str_contains($contentType, 'image')
            || str_contains($contentType, 'icon')
            || str_contains($contentType, 'octet-stream');

        return $isImage ? $body : null;
    }

    /**
     * The icon the page declares, as its URL plus the bytes that answered
     * there — see locate() for why the bytes come along.
     *
     * @param  Closure(string): bool|null  $accept  See locate().
     * @return array{url: string, body: string}|null
     */
    protected function resolveFromHtml(string $pageUrl, ?Closure $accept = null): ?array
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

        $body = $this->limitedBody($response);

        if (! preg_match_all('/<link\b[^>]*>/i', $body, $matches)) {
            return null;
        }

        // A document may point its relative URLs somewhere else entirely, and
        // the icon href is one of them.
        $reference = $this->documentBaseUrl($body, $finalUrl);

        $candidates = [];

        foreach ($matches[0] as $tag) {
            $rel = $this->attributeValue($tag, 'rel');

            if ($rel === null || preg_match('/\bicon\b/i', $rel) !== 1) {
                continue;
            }

            $href = $this->attributeValue($tag, 'href');

            if (blank($href)) {
                continue;
            }

            $candidate = $this->makeAbsoluteUrl(html_entity_decode($href), $reference);

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
            // several rel variants must not eat the probe budget. The best rank
            // an href is declared with is the one it keeps.
            $rank = $this->iconRelRank($rel);

            $candidates[$candidate] = min($candidates[$candidate] ?? PHP_INT_MAX, $rank);

            if (count($candidates) >= self::MAX_ICON_CANDIDATES) {
                break;
            }
        }

        // Rank before document order: a page commonly declares its
        // apple-touch-icon first, and that one is a 180×180 home-screen tile,
        // not the tab icon this package renders at ~16px. Taking it because it
        // came first stores several times the bytes for a worse marker — and,
        // when it is over the embed cap, loses to a real icon further down the
        // list. asort keeps document order within a rank.
        asort($candidates);

        foreach (array_keys($candidates) as $candidate) {
            if ($this->isOutOfTime()) {
                break;
            }

            // The candidate must resolve to a real image, so a wrong href isn't
            // saved as a silently-broken favicon.
            $body = $this->fetchFavicon($candidate);

            if ($body !== null && ($accept === null || $accept($body))) {
                return ['url' => $candidate, 'body' => $body];
            }
        }

        return null;
    }

    /**
     * How much a `rel` value looks like the icon a browser shows in the tab,
     * lower being better. This is the whole ordering: `rel="icon"` and its
     * legacy `shortcut icon` spelling are what the page nominates as its tab
     * icon; `apple-touch-icon` is a home-screen tile at 180×180; `mask-icon`
     * and the rest are single-colour or platform-specific and worse still.
     *
     * Matched on whole `rel` tokens, the way a browser reads the attribute, so
     * `apple-touch-icon` never counts as a plain `icon`.
     */
    protected function iconRelRank(string $rel): int
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];

        if (in_array('icon', $tokens, true)) {
            return 0;
        }

        foreach ($tokens as $token) {
            if (str_starts_with($token, 'apple-touch-icon')) {
                return 1;
            }
        }

        return 2;
    }

    /**
     * Read one attribute out of a start tag, the way an HTML parser does:
     * double-quoted, single-quoted or unquoted.
     *
     * The unquoted form is the reason this exists. `<link rel=icon
     * href=/favicon.svg>` is valid HTML and is exactly what an HTML minifier
     * emits — a pattern that insists on quotes sees no icon on such a page at
     * all and silently falls through to guessing at file names.
     *
     * The name is matched with a `[\w-]` guard rather than `\b`, so `data-href`
     * is not read as `href`.
     *
     * @return string|null Null when the tag carries no such attribute; the
     *                     empty string when it carries an empty one.
     */
    protected function attributeValue(string $tag, string $attribute): ?string
    {
        $pattern = '/(?<![\w-])'.preg_quote($attribute, '/').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]*))/i';

        if (preg_match($pattern, $tag, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        return $match[1] ?? $match[2] ?? $match[3] ?? '';
    }

    /**
     * The URL the document's own relative references resolve against: its
     * `<base href>` when it declares a usable one, otherwise the URL that
     * answered.
     *
     * A single-page app served from a sub-path is the case that needs this. Its
     * shell sets `<base href="/admin/">` and then declares `href="favicon.ico"`,
     * which means `/admin/favicon.ico` in a browser and `/favicon.ico` to anyone
     * who ignores the tag — the site-wide icon that asking the page first exists
     * to stop preferring.
     *
     * Only the first `<base>` counts, the way a browser reads it, and only an
     * http(s) one with a host is honored: a `data:` or malformed base leaves the
     * answering URL in place rather than producing candidates with no host for
     * the SSRF check to judge. A base pointing at another host is honored — that
     * is what a browser does — and every candidate built from it still goes
     * through that check before a request is made.
     */
    protected function documentBaseUrl(string $html, string $finalUrl): string
    {
        if (! preg_match('/<base\b[^>]*>/i', $html, $match)) {
            return $finalUrl;
        }

        $href = $this->attributeValue($match[0], 'href');

        if (blank($href)) {
            return $finalUrl;
        }

        $base = $this->makeAbsoluteUrl(html_entity_decode($href), $finalUrl);

        if ($base === null) {
            return $finalUrl;
        }

        $scheme = strtolower((string) parse_url($base, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || blank(parse_url($base, PHP_URL_HOST))) {
            return $finalUrl;
        }

        return $base;
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

        // The query and the fragment are split off before any of the path
        // arithmetic below. normalizePath() resolves `.` and `..` segments, and
        // those are ordinary characters inside a query value: an icon href of
        // `/icon.png?a=/../b` came back as `/b`, a different resource
        // altogether, and a Cloudflare image href
        // (`/cdn-cgi/image/width=32/https://host/icon.png`) lost a slash. The
        // suffix is carried through verbatim.
        [$path, $suffix] = $this->splitReference($href);

        // Query-only (`?v=2`) or fragment-only (`#x`): the base path stands and
        // only the part given is replaced (RFC 3986 §5.3). Resolving these
        // against the page's DIRECTORY, as a path-relative reference, pointed at
        // `/app/?v=2` where a browser reads `/app/index.php?v=2`.
        if ($path === '') {
            $pagePath = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
            $pageQuery = parse_url($pageUrl, PHP_URL_QUERY);

            // A fragment keeps the base query as well; a query replaces it.
            if (str_starts_with($suffix, '#') && filled($pageQuery)) {
                $pagePath .= '?'.$pageQuery;
            }

            return $authority.$pagePath.$suffix;
        }

        // Root-relative: /icon.png
        if (str_starts_with($path, '/')) {
            return $authority.$this->normalizePath($path).$suffix;
        }

        // Path-relative: resolve against the page's directory.
        $pagePath = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        $directory = substr($pagePath, 0, strrpos($pagePath, '/') + 1) ?: '/';

        return $authority.$this->normalizePath($directory.$path).$suffix;
    }

    /**
     * Split a reference into its path and everything from the first `?` or `#`
     * onwards, so only the path is put through normalizePath().
     *
     * @return array{string, string}
     */
    protected function splitReference(string $reference): array
    {
        $length = strcspn($reference, '?#');

        return [substr($reference, 0, $length), substr($reference, $length)];
    }

    /**
     * Resolve `.` and `..` segments in an absolute path, the way RFC 3986's
     * remove_dot_segments does, keeping a trailing slash where the path has one.
     *
     * The slash is not cosmetic: it is what makes a path a directory, and every
     * relative reference underneath it resolves one level differently without it
     * (`/admin/` + `favicon.ico` is `/admin/favicon.ico`, `/admin` + the same is
     * `/favicon.ico`). A path ending in `.` or `..` denotes a directory too.
     *
     * An EMPTY segment is kept, unlike `.` and `..`. `//` inside a path is a
     * literal part of it — a Cloudflare image href is
     * `/cdn-cgi/image/width=32/https://host/icon.png` — and dropping it built a
     * URL the origin does not serve, so the page's declared icon was quietly
     * skipped in favour of guessing at conventional file names. Only the leading
     * empty segment is dropped, because that one is the root marker rather than
     * a segment.
     */
    protected function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $input = explode('/', $path);

        if ($input[0] === '') {
            array_shift($input);
        }

        $segments = [];
        $endsAtDirectory = false;

        foreach ($input as $segment) {
            if ($segment === '.') {
                $endsAtDirectory = true;

                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                $endsAtDirectory = true;

                continue;
            }

            $segments[] = $segment;
            $endsAtDirectory = false;
        }

        $normalized = '/'.implode('/', $segments);

        if ($endsAtDirectory && ! str_ends_with($normalized, '/')) {
            $normalized .= '/';
        }

        return $normalized;
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
                if ($this->isOutOfTime()) {
                    return null;
                }

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
            $this->unwrapEmbeddedIpv4($ip),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Reduce an IPv6 address that carries an IPv4 one inside it to that IPv4
     * address, so the range flags judge what the packet actually reaches.
     *
     * They cannot be trusted to do it themselves. On PHP 8.2 — a version this
     * package supports — `filter_var('::ffff:127.0.0.1', FILTER_VALIDATE_IP,
     * FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)` returns the
     * address, calling loopback public; `::ffff:10.0.0.1` and the deprecated
     * `::127.0.0.1` form pass the same way. PHP 8.3 added `::ffff:0:0/96` to
     * its reserved list and closed that, which is why the hole is invisible on
     * a newer runtime. Unwrapping here fixes 8.2 and leaves 8.3+ unchanged.
     *
     * The NAT64 well-known prefix is handled too, and that one is open on every
     * version: on a network with a NAT64 gateway, `64:ff9b::7f00:1` is how a
     * host reaches 127.0.0.1 over IPv6.
     *
     * `::` and `::1` fall out of the IPv4-compatible branch as `0.0.0.0` and
     * `0.0.0.1`, both inside the reserved `0.0.0.0/8` — still refused.
     */
    protected function unwrapEmbeddedIpv4(string $ip): string
    {
        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }

        $prefixes = [
            str_repeat("\0", 10)."\xff\xff",          // ::ffff:0:0/96, IPv4-mapped
            str_repeat("\0", 12),                     // ::/96, IPv4-compatible (deprecated)
            "\x00\x64\xff\x9b".str_repeat("\0", 8),   // 64:ff9b::/96, NAT64 well-known
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($packed, $prefix)) {
                return (string) inet_ntop(substr($packed, 12));
            }
        }

        return $ip;
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
     * Whether a resolved favicon URL is safe to persist and later render.
     *
     * The rule lives on the model, next to the embedded-favicon gate, because
     * three places need it: this resolver on the way in, the import on an
     * uploaded file, and displayIcon() on the way out. Three copies is how they
     * drift — and they had: this one accepted `http:favicon.ico` (scheme, no
     * host) that the import refused, and spelled the length bound as a literal
     * 255 where the import used a named constant.
     */
    protected function isStorableFaviconUrl(string $url): bool
    {
        return TopbarMenuItem::isStorableFaviconUrl($url);
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
