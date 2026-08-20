<?php

namespace Vaslv\FilamentTopbarMenu\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

class RefreshFaviconsCommand extends Command
{
    protected $signature = 'filament-topbar-menu:refresh-favicons
        {--id=* : Only refresh the menu items with the given IDs}
        {--force : Refresh favicons for items that already have one}
        {--embed : Store the icon itself in the record instead of a link to it}
        {--timeout= : Stop after this many seconds and report what was done}';

    protected $description = 'Resolve and store favicons for external topbar menu links';

    public function handle(FaviconResolver $resolver): int
    {
        if (! config('filament-topbar-menu.enable_favicons', true)) {
            // A no-op, not an error: the command is safe to leave in deploy
            // scripts and schedulers even when the feature is turned off.
            $this->components->warn(__('filament-topbar-menu::filament-topbar-menu.command.disabled'));

            return self::SUCCESS;
        }

        // --embed stores the icon's own bytes in `favicon_data` instead of a
        // link in `favicon_url`, for links whose icon a visitor's browser
        // cannot fetch. The column it fills is also the column that decides
        // which items still need one.
        $embed = (bool) $this->option('embed');
        $column = $embed ? 'favicon_data' : 'favicon_url';

        $query = TopbarMenuItem::query()
            ->where('type', TopbarMenuItem::TYPE_URL)
            ->whereNotNull('url');

        if (filled($ids = $this->option('id'))) {
            $query->whereIn('id', $ids);
        }

        if (! $this->option('force')) {
            $query->whereNull($column);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.nothing_to_refresh'));

            return self::SUCCESS;
        }

        // Escaped because a published/custom translation could contain `<`/`>`,
        // which would otherwise be parsed as Symfony console style tags.
        $notFound = OutputFormatter::escape(
            __('filament-topbar-menu::filament-topbar-menu.command.not_found'),
        );

        $deadline = $this->deadline();

        // Events off for the whole run, with a single flush at the end. Each
        // save fired the model's saved hook, so a run over N items flushed the
        // menu cache N times — every one of them rebuilding a snapshot that the
        // next save would drop again.
        $resolved = TopbarMenuItem::withoutEvents(function () use ($items, $resolver, $embed, $column, $notFound, $deadline): int {
            $resolved = 0;

            foreach ($items as $item) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    break;
                }

                $favicon = $embed
                    ? $resolver->resolveEmbedded($item->url)
                    : $resolver->resolve($item->url);

                // Escape the admin-controlled label (and the favicon) before they
                // reach the console: a stored value containing `<`/`>` would
                // otherwise be parsed as Symfony style tags and could throw mid-run.
                // Same reason the not-found string above is escaped.
                $label = OutputFormatter::escape($item->label);

                if ($favicon === null) {
                    $this->components->twoColumnDetail($label, "<fg=yellow>{$notFound}</>");

                    continue;
                }

                $item->forceFill([$column => $favicon])->save();
                $resolved++;

                // An embedded icon is thousands of base64 characters — report what
                // was stored, not the payload itself.
                $this->components->twoColumnDetail($label, $embed
                    ? OutputFormatter::escape(__('filament-topbar-menu::filament-topbar-menu.command.embedded', [
                        'type' => $this->embeddedType($favicon),
                    ]))
                    : OutputFormatter::escape($favicon));
            }

            return $resolved;
        });

        if ($resolved > 0) {
            app(TopbarMenu::class)->flushCache();
        }

        // "Resolved 3 of 50" is also how a run that ran out of time reports
        // itself: the totals are what was asked for, not what was reached.
        $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.resolved_summary', [
            'resolved' => $resolved,
            'total' => $items->count(),
        ]));

        return self::SUCCESS;
    }

    /**
     * When the run must stop, or null for no limit.
     *
     * Unbounded by default, unlike the panel's bulk action: a CLI run is not
     * something a person is holding a browser tab open for, and the whole point
     * of pointing an admin at this command is that it can take as long as it
     * takes. `--timeout` is for the deploy script and the scheduler, where a run
     * that never ends is a run that blocks everything behind it.
     */
    protected function deadline(): ?float
    {
        $timeout = $this->option('timeout');

        if (! is_numeric($timeout) || (float) $timeout <= 0) {
            return null;
        }

        return microtime(true) + (float) $timeout;
    }

    /**
     * The image type of an embedded favicon, for the console line that stands
     * in for the payload.
     */
    protected function embeddedType(string $data): string
    {
        return str($data)->after('data:')->before(';')->toString();
    }
}
