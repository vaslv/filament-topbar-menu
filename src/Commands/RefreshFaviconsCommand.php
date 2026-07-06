<?php

namespace Vaslv\FilamentTopbarMenu\Commands;

use Illuminate\Console\Command;
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
            $this->components->warn('Favicon resolution is disabled (filament-topbar-menu.enable_favicons); nothing to do.');

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
            $this->components->info('No menu items need a favicon refresh.');

            return self::SUCCESS;
        }

        $resolved = 0;

        foreach ($items as $item) {
            $favicon = $resolver->resolve($item->url);

            if ($favicon === null) {
                $this->components->twoColumnDetail($item->label, '<fg=yellow>not found</>');

                continue;
            }

            $item->forceFill(['favicon_url' => $favicon])->save();
            $resolved++;

            $this->components->twoColumnDetail($item->label, $favicon);
        }

        $this->components->info("Resolved {$resolved} of {$items->count()} favicon(s).");

        return self::SUCCESS;
    }
}
