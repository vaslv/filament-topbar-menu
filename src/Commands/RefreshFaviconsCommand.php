<?php

namespace Vaslv\FilamentTopbarMenu\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;

class RefreshFaviconsCommand extends Command
{
    protected $signature = 'filament-topbar-menu:refresh-favicons
        {--id=* : Only refresh the menu items with the given IDs}
        {--force : Refresh favicons for items that already have one}';

    protected $description = 'Resolve and store favicons for external topbar menu links';

    public function handle(FaviconResolver $resolver): int
    {
        if (! config('filament-topbar-menu.enable_favicons', true)) {
            // A no-op, not an error: the command is safe to leave in deploy
            // scripts and schedulers even when the feature is turned off.
            $this->components->warn(__('filament-topbar-menu::filament-topbar-menu.command.disabled'));

            return self::SUCCESS;
        }

        $query = TopbarMenuItem::query()
            ->where('type', TopbarMenuItem::TYPE_URL)
            ->whereNotNull('url');

        if (filled($ids = $this->option('id'))) {
            $query->whereIn('id', $ids);
        }

        if (! $this->option('force')) {
            $query->whereNull('favicon_url');
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.nothing_to_refresh'));

            return self::SUCCESS;
        }

        $resolved = 0;
        // Escaped because a published/custom translation could contain `<`/`>`,
        // which would otherwise be parsed as Symfony console style tags.
        $notFound = OutputFormatter::escape(
            __('filament-topbar-menu::filament-topbar-menu.command.not_found'),
        );

        foreach ($items as $item) {
            $favicon = $resolver->resolve($item->url);

            if ($favicon === null) {
                $this->components->twoColumnDetail($item->label, "<fg=yellow>{$notFound}</>");

                continue;
            }

            $item->forceFill(['favicon_url' => $favicon])->save();
            $resolved++;

            $this->components->twoColumnDetail($item->label, $favicon);
        }

        $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.resolved_summary', [
            'resolved' => $resolved,
            'total' => $items->count(),
        ]));

        return self::SUCCESS;
    }
}
