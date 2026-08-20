<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;
use Vaslv\FilamentTopbarMenu\TopbarMenu;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

class TopbarMenuItemResource extends Resource
{
    protected static ?string $model = TopbarMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    public static function getModelLabel(): string
    {
        return static::trans('labels.model');
    }

    public static function getPluralModelLabel(): string
    {
        return static::trans('labels.plural_model');
    }

    public static function getNavigationLabel(): string
    {
        return static::trans('labels.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()?->getResourceNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getResourceNavigationSort() ?? parent::getNavigationSort();
    }

    /**
     * Short helper for the package's translation namespace.
     *
     * @param  array<string, mixed>  $replace
     */
    protected static function trans(string $key, array $replace = []): string
    {
        return __("filament-topbar-menu::filament-topbar-menu.{$key}", $replace);
    }

    protected static function plugin(): ?TopbarMenuPlugin
    {
        $panel = filament()->getCurrentOrDefaultPanel();

        if (! $panel?->hasPlugin('filament-topbar-menu')) {
            return null;
        }

        /** @var TopbarMenuPlugin $plugin */
        $plugin = $panel->getPlugin('filament-topbar-menu');

        return $plugin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(static::trans('sections.link'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('label')
                            ->label(static::trans('fields.label'))
                            ->required()
                            ->maxLength(255),

                        Select::make('parent_id')
                            ->label(static::trans('fields.parent.label'))
                            ->placeholder(static::trans('fields.parent.placeholder'))
                            ->options(
                                fn (?TopbarMenuItem $record): array => TopbarMenuItem::query()
                                    ->root()
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->ordered()
                                    ->pluck('label', 'id')
                                    ->all(),
                            )
                            ->searchable()
                            ->nullable()
                            ->helperText(static::trans('fields.parent.helper')),

                        Select::make('type')
                            ->label(static::trans('fields.type.label'))
                            ->options([
                                TopbarMenuItem::TYPE_URL => static::trans('fields.type.options.url'),
                                TopbarMenuItem::TYPE_ROUTE => static::trans('fields.type.options.route'),
                            ])
                            ->default(TopbarMenuItem::TYPE_URL)
                            ->required()
                            ->live(),

                        Select::make('target')
                            ->label(static::trans('fields.target.label'))
                            ->options([
                                TopbarMenuItem::TARGET_SELF => static::trans('fields.target.options.self'),
                                TopbarMenuItem::TARGET_BLANK => static::trans('fields.target.options.blank'),
                            ])
                            // The config only seeds the default for new items; the
                            // stored per-item choice is always honored at render.
                            ->default(fn (): string => config('filament-topbar-menu.open_external_links_in_new_tab', true)
                                ? TopbarMenuItem::TARGET_BLANK
                                : TopbarMenuItem::TARGET_SELF)
                            ->required(),

                        TextInput::make('url')
                            ->label(static::trans('fields.url.label'))
                            ->placeholder(static::trans('fields.url.placeholder'))
                            ->url()
                            // Laravel's bare `url` rule accepts a long list of
                            // schemes — `data://`, `view-source://`, `jar://`
                            // and dozens more. None of them belongs in a menu
                            // link, and the form is the guided path: it asks for
                            // the absolute external URL the field is for, and
                            // leaves the wider set the renderer tolerates to the
                            // writers that need it (a seeder, an import).
                            ->rule('url:http,https')
                            // The column is VARCHAR(255). Without this a long
                            // but perfectly valid tracking URL is truncated in
                            // silence on MySQL in non-strict mode, and a raw
                            // driver exception — a 500 on the form — everywhere
                            // else. The import has always enforced this bound;
                            // the primary write path did not.
                            ->maxLength(TopbarMenuItem::URL_MAX_LENGTH)
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_URL)
                            // A dropdown group's own link is never used (the
                            // toggle does not navigate), so an item that already
                            // has children may be saved without one.
                            ->required(fn (Get $get, ?TopbarMenuItem $record): bool => $get('type') === TopbarMenuItem::TYPE_URL
                                && ! $record?->children()->exists())
                            ->columnSpanFull(),

                        Select::make('route')
                            ->label(static::trans('fields.route.label'))
                            ->options(
                                fn (): array => collect(Route::getRoutes()->getRoutesByName())
                                    ->keys()
                                    ->reject(fn (string $name): bool => str_contains($name, 'livewire'))
                                    ->sort()
                                    ->mapWithKeys(fn (string $name): array => [$name => $name])
                                    ->all(),
                            )
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_ROUTE)
                            // Same as `url`: optional for existing dropdown groups.
                            ->required(fn (Get $get, ?TopbarMenuItem $record): bool => $get('type') === TopbarMenuItem::TYPE_ROUTE
                                && ! $record?->children()->exists()),

                        KeyValue::make('route_parameters')
                            ->keyLabel(static::trans('fields.route_parameters.key'))
                            ->valueLabel(static::trans('fields.route_parameters.value'))
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_ROUTE)
                            ->nullable(),
                    ]),

                Section::make(static::trans('sections.appearance'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('icon')
                            ->label(static::trans('fields.icon.label'))
                            ->placeholder(static::trans('fields.icon.placeholder'))
                            ->maxLength(TopbarMenuItem::URL_MAX_LENGTH)
                            // Escape the translator-controlled text FIRST, then
                            // substitute the hardcoded anchor — so an overridden
                            // or third-party locale stays HTML-inert instead of
                            // becoming a raw-HTML sink in the admin panel.
                            ->helperText(str(e(static::trans('fields.icon.helper')))
                                ->replace(':link', '<a href="https://heroicons.com" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline dark:text-primary-400">heroicons.com</a>')
                                ->toHtmlString()),

                        TextInput::make('favicon_url')
                            ->label(static::trans('fields.favicon_url.label'))
                            ->url()
                            // The same two bounds as `url`, and here they are
                            // also what displayIcon() will demand at render
                            // time: a value the form accepts but the renderer
                            // refuses is an icon that silently never appears.
                            ->rule('url:http,https')
                            ->maxLength(TopbarMenuItem::URL_MAX_LENGTH)
                            ->suffixAction(
                                Action::make('fetchFavicon')
                                    ->label(static::trans('actions.fetch_favicon'))
                                    ->icon('heroicon-m-arrow-path')
                                    ->tooltip(static::trans('actions.fetch_favicon_tooltip'))
                                    ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                                    ->action(function (Get $get, Set $set): void {
                                        $url = $get('url');

                                        if (blank($url)) {
                                            Notification::make()
                                                ->title(static::trans('notifications.enter_url_first'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $favicon = app(FaviconResolver::class)->resolve($url);

                                        if ($favicon === null) {
                                            Notification::make()
                                                ->title(static::trans('notifications.favicon_not_found'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $set('favicon_url', $favicon);

                                        Notification::make()
                                            ->title(static::trans('notifications.favicon_resolved'))
                                            ->success()
                                            ->send();
                                    }),
                            ),

                        // The embedded icon: the image's own bytes, for links
                        // whose icon a visitor's browser cannot fetch (a host
                        // behind HTTP auth, an internal-only service). Stored in
                        // the record, never on a disk — the deployments that
                        // need this are the ones without persistent file
                        // storage, and a `data:` URI travels with the menu
                        // through export/import and the cached snapshot.
                        //
                        // Three components rather than one FileUpload, because
                        // the stored value is a `data:` URI and FileUpload can
                        // neither display one as its state nor round-trip it:
                        // Hidden holds the value, the upload writes into it from
                        // its own afterStateUpdated() below, and the preview
                        // shows what is actually stored.
                        //
                        // The value is validated on the way in as well as on the
                        // way out. A Hidden field's state is client-controlled —
                        // a panel user can PATCH `data.favicon_data` to anything
                        // — and while displayIcon() would refuse a bad value at
                        // render time, without this the row still gets written,
                        // uncapped, into the column that rides in the cached
                        // menu snapshot.
                        Hidden::make('favicon_data')
                            ->dehydrateStateUsing(fn (mixed $state): ?string => is_string($state)
                                && TopbarMenuItem::isStorableFaviconData($state)
                                    ? $state
                                    : null),

                        // A TextEntry inside the form, not a Placeholder: that
                        // one is a deprecated alias of this class in Filament 5.
                        TextEntry::make('favicon_data_preview')
                            ->label(static::trans('fields.favicon_data.label'))
                            ->state(fn (Get $get): HtmlString => static::faviconDataPreview($get('favicon_data')))
                            ->hintAction(
                                Action::make('clearFaviconData')
                                    ->label(static::trans('actions.clear_favicon_data'))
                                    ->icon('heroicon-m-x-mark')
                                    ->color('gray')
                                    ->visible(fn (Get $get): bool => filled($get('favicon_data')))
                                    ->action(fn (Set $set) => $set('favicon_data', null)),
                            ),

                        FileUpload::make('favicon_upload')
                            ->label(static::trans('fields.favicon_data.upload'))
                            ->helperText(static::trans('fields.favicon_data.helper'))
                            ->image()
                            ->acceptedFileTypes(TopbarMenuItem::FAVICON_DATA_TYPES)
                            // Filament's own limit is in kilobytes; the byte cap
                            // is the authority, and the model re-checks it on the
                            // decoded bytes, so this only spares the admin an
                            // upload that would be rejected on save anyway.
                            ->maxSize((int) ceil(TopbarMenuItem::faviconMaxBytes() / 1024))
                            // No disk write: the state stays a
                            // TemporaryUploadedFile, which the closure below
                            // reads and turns into the stored `data:` URI.
                            ->storeFiles(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                // FileUpload keeps its state as an array keyed
                                // by upload id even when it accepts one file.
                                $file = is_array($state) ? Arr::first($state) : $state;

                                // Removing the file runs this same closure with
                                // an empty state (BaseFileUpload::removeUploadedFile
                                // calls it right after unsetting the file), so
                                // this is also the "clear it" path. Returning
                                // early here would leave the icon the admin just
                                // removed still stored and still rendering.
                                if (blank($file)) {
                                    $set('favicon_data', null);

                                    return;
                                }

                                if (! $file instanceof TemporaryUploadedFile) {
                                    return;
                                }

                                $data = TopbarMenuItem::faviconDataFromBytes((string) $file->get());

                                if ($data === null) {
                                    Notification::make()
                                        ->title(static::trans('notifications.favicon_upload_rejected', [
                                            'size' => static::formatBytes(TopbarMenuItem::faviconMaxBytes()),
                                        ]))
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $set('favicon_data', $data);
                            })
                            ->hintAction(
                                Action::make('embedFavicon')
                                    ->label(static::trans('actions.embed_favicon'))
                                    ->icon('heroicon-m-arrow-down-tray')
                                    ->tooltip(static::trans('actions.embed_favicon_tooltip'))
                                    ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                                    ->action(function (Get $get, Set $set): void {
                                        $url = $get('url');

                                        if (blank($url)) {
                                            Notification::make()
                                                ->title(static::trans('notifications.enter_url_first'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $data = app(FaviconResolver::class)->resolveEmbedded($url);

                                        if ($data === null) {
                                            Notification::make()
                                                ->title(static::trans('notifications.favicon_not_found'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $set('favicon_data', $data);

                                        Notification::make()
                                            ->title(static::trans('notifications.favicon_embedded'))
                                            ->success()
                                            ->send();
                                    }),
                            )
                            ->columnSpanFull(),

                        // A Select cannot bind to the `visibility` JSON column
                        // directly: Filament pipes a Select's raw state through
                        // OptionStateCast (strval) during hydration — before
                        // formatStateUsing runs — so an array state crashes with
                        // "Array to string conversion". The form edits a virtual
                        // `visibility_mode` string instead; the Create/Edit pages
                        // map it from and back onto the `visibility` array,
                        // preserving keys the form does not manage (`roles`).
                        Select::make('visibility_mode')
                            ->label(static::trans('fields.visibility.label'))
                            ->placeholder(static::trans('fields.visibility.placeholder'))
                            ->options([
                                'auth' => static::trans('fields.visibility.options.auth'),
                                'guest' => static::trans('fields.visibility.options.guest'),
                            ])
                            ->nullable()
                            ->helperText(static::trans('fields.visibility.helper')),

                        // Same virtual-field workaround as `visibility_mode`
                        // above: the toggle maps from and back onto the
                        // `visibility` JSON array in the Create/Edit pages.
                        // URL items only — the rule never applies to route
                        // items, so the toggle is not offered for them; a
                        // stored value survives the type switch untouched
                        // (see EditTopbarMenuItem::mutateFormDataBeforeSave).
                        Toggle::make('visibility_hide_on_current_domain')
                            ->label(static::trans('fields.hide_on_current_domain.label'))
                            ->helperText(static::trans('fields.hide_on_current_domain.helper'))
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_URL)
                            ->inline(false),

                        TextInput::make('sort')
                            ->label(static::trans('fields.sort.label'))
                            // integer(), not numeric(): numeric() adds only the
                            // `numeric` rule and `step: any`, and nothing casts
                            // afterwards — the model's `integer` cast applies on
                            // read, not on write. So `2.5` reached
                            // `$table->integer('sort')` verbatim: a driver error
                            // on PostgreSQL and strict MySQL, and on SQLite a
                            // stored 2.5 that ordered() sorts by and the admin
                            // can never reproduce through the UI.
                            ->integer()
                            // The column's own range. Past it the insert fails
                            // with a raw overflow error rather than a field
                            // message.
                            ->minValue(-2147483648)
                            ->maxValue(2147483647)
                            ->default(0)
                            ->helperText(static::trans('fields.sort.helper')),

                        Toggle::make('is_active')
                            ->label(static::trans('fields.is_active.label'))
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    /**
     * Whether the read-only View page is part of the resource — on by default,
     * switched off with `filament-topbar-menu.enable_view_page` (the
     * FILAMENT_TOPBAR_MENU_VIEW_PAGE env variable). It governs the `{record}`
     * route, the View actions and the post-save landing page in one place, so
     * a panel that turns it off never renders a link to a route that is not
     * registered.
     */
    public static function hasViewPage(): bool
    {
        if (! (bool) config('filament-topbar-menu.enable_view_page', true)) {
            return false;
        }

        // Route-cache skew, the one way this feature can 500 a production
        // panel: the route comes from getPages() at boot, but an application
        // that updates the package without re-running `route:cache` keeps a
        // route collection that predates this page. Filament would still read
        // hasPage('view') as true — through this method, through its own
        // breadcrumbs, and through CreateRecord's redirect, which runs AFTER
        // the record is written — and generating the URL of a route the router
        // does not know throws. Answering from the router in that case degrades
        // the feature to "no View page until the cache is rebuilt".
        //
        // Only once the application is booted, though: Laravel requires the
        // cached route file from an `booted` callback, so during boot — which
        // is when Filament reads getPages() to register the pages' Livewire
        // components — the router is still empty and the check would strip the
        // page off a perfectly healthy cached deploy.
        if (! app()->routesAreCached() || ! app()->isBooted()) {
            return true;
        }

        try {
            return Route::has(static::getRouteBaseName().'.view');
        } catch (Throwable) {
            // No panel context (the resource inspected outside a panel, as this
            // package's own unit tests do): nothing can link anywhere anyway.
            return false;
        }
    }

    /**
     * The role names a record is restricted to, keeping only values that can
     * actually be shown and matched — `roles` is written by an import or
     * directly on the record, so the column can hold anything.
     *
     * @return list<string>
     */
    protected static function rolesOf(TopbarMenuItem $record): array
    {
        return array_values(array_filter(
            (array) ($record->visibility['roles'] ?? []),
            fn (mixed $role): bool => is_string($role) && $role !== '',
        ));
    }

    /**
     * The embedded icon rendered at menu size, or a dash when the field holds
     * nothing this package would render.
     *
     * The value is validated before it reaches the markup, not merely escaped:
     * the same check displayIcon() applies, so the form preview cannot show an
     * icon the topbar would refuse. Escaping stays on top of it because a
     * preview is HTML this method builds by hand.
     */
    protected static function faviconDataPreview(mixed $value): HtmlString
    {
        if (! is_string($value) || ! TopbarMenuItem::isStorableFaviconData($value)) {
            return new HtmlString('—');
        }

        return new HtmlString(
            '<img src="'.e($value).'" alt="" class="h-8 w-8 rounded object-contain">',
        );
    }

    /**
     * Resolve favicon LINKS for the selected records and report how many were
     * stored — and, separately, how many of them will not actually change what
     * the topbar shows.
     *
     * That second count is the point: displayIcon() prefers an embedded icon
     * over a link, so storing a link for an item that carries one succeeds and
     * changes nothing visible. Reporting "5 resolved" while the menu stays put
     * is the kind of silence an admin cannot debug, so the count says so.
     *
     * @param  Collection<int, TopbarMenuItem>  $records
     */
    protected static function resolveFaviconsFor(Collection $records): void
    {
        $resolver = app(FaviconResolver::class);
        $resolved = 0;
        $overridden = 0;

        foreach ($records as $record) {
            if ($record->type !== TopbarMenuItem::TYPE_URL || blank($record->url)) {
                continue;
            }

            $favicon = $resolver->resolve($record->url);

            if ($favicon === null) {
                continue;
            }

            $record->forceFill(['favicon_url' => $favicon])->save();
            $resolved++;

            if (TopbarMenuItem::isStorableFaviconData($record->favicon_data)) {
                $overridden++;
            }
        }

        $notification = Notification::make()
            ->title(static::trans('notifications.favicons_resolved', ['count' => $resolved]))
            ->success();

        if ($overridden > 0) {
            $notification->body(static::trans('notifications.favicons_overridden', ['count' => $overridden]));
        }

        $notification->send();
    }

    /**
     * A byte count as an admin-readable size, for the message that explains a
     * rejected upload.
     */
    protected static function formatBytes(int $bytes): string
    {
        return $bytes >= 1024
            ? round($bytes / 1024).' KB'
            : $bytes.' B';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(static::trans('sections.link'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('label')
                            ->label(static::trans('fields.label')),

                        TextEntry::make('parent.label')
                            ->label(static::trans('columns.parent'))
                            ->placeholder('—'),

                        TextEntry::make('type')
                            ->label(static::trans('fields.type.label'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => static::trans("fields.type.options.{$state}"))
                            ->color(fn (string $state): string => $state === TopbarMenuItem::TYPE_ROUTE ? 'info' : 'gray'),

                        TextEntry::make('target')
                            ->label(static::trans('fields.target.label'))
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (string $state): string => static::trans('fields.target.options.'.($state === TopbarMenuItem::TARGET_BLANK ? 'blank' : 'self'))),

                        TextEntry::make('url')
                            ->label(static::trans('fields.url.label'))
                            ->visible(fn (TopbarMenuItem $record): bool => $record->type === TopbarMenuItem::TYPE_URL)
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('route')
                            ->label(static::trans('fields.route.label'))
                            ->visible(fn (TopbarMenuItem $record): bool => $record->type === TopbarMenuItem::TYPE_ROUTE)
                            ->placeholder('—'),

                        KeyValueEntry::make('route_parameters')
                            ->keyLabel(static::trans('fields.route_parameters.key'))
                            ->valueLabel(static::trans('fields.route_parameters.value'))
                            ->visible(fn (TopbarMenuItem $record): bool => $record->type === TopbarMenuItem::TYPE_ROUTE
                                && filled($record->route_parameters)),

                        // What the topbar actually links to: a route item's URL
                        // is generated, and a broken route name resolves to
                        // null — the page is where an admin finds that out
                        // instead of hunting for a missing item in the menu.
                        TextEntry::make('resolved_url')
                            ->label(static::trans('columns.resolved_url'))
                            ->state(fn (TopbarMenuItem $record): ?string => $record->resolveUrl())
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make(static::trans('sections.appearance'))
                    ->columns(2)
                    ->schema([
                        // Same marker the topbar renders, resolved the same way
                        // (favicon over icon) — see displayIcon(). As in the
                        // table, displayIcon() has to be the entry's STATE:
                        // IconEntry::toEmbeddedHtml() returns the placeholder
                        // for a blank state before it ever calls ->icon().
                        IconEntry::make('display_icon')
                            ->label(static::trans('fields.icon.label'))
                            ->state(fn (TopbarMenuItem $record): ?string => $record->displayIcon())
                            ->icon(fn (?string $state): ?string => $state)
                            ->placeholder('—'),

                        TextEntry::make('favicon_url')
                            ->label(static::trans('fields.favicon_url.label'))
                            ->placeholder('—'),

                        // The stored bytes are never shown as text — a `data:`
                        // URI is thousands of characters. What matters on this
                        // page is that an embedded icon exists (it overrides the
                        // URL above) and what it looks like.
                        // No ->placeholder() here: the state is always an
                        // HtmlString, never blank, so Filament would never reach
                        // one. faviconDataPreview() returns the dash itself.
                        TextEntry::make('favicon_data')
                            ->label(static::trans('fields.favicon_data.label'))
                            ->state(fn (TopbarMenuItem $record): HtmlString => static::faviconDataPreview($record->favicon_data)),

                        TextEntry::make('visibility_mode')
                            ->label(static::trans('fields.visibility.label'))
                            ->state(fn (TopbarMenuItem $record): ?string => TopbarMenuItem::visibilityModeFromArray($record->visibility))
                            ->formatStateUsing(fn (string $state): string => static::trans("fields.visibility.options.{$state}"))
                            // No mode stored means "everyone" — the same word
                            // the form's placeholder uses. Except when roles
                            // restrict the item: the mode ignores that key, so
                            // saying "Everyone" here would contradict the roles
                            // entry right below and overstate who sees the item.
                            ->placeholder(fn (TopbarMenuItem $record): string => static::rolesOf($record) === []
                                ? static::trans('fields.visibility.placeholder')
                                : '—'),

                        // The audience above only knows auth/guest, so without
                        // this entry a role-restricted item reads as "Everyone"
                        // — the page would understate who can see it. `roles` is
                        // set through an import or directly on the record (the
                        // form deliberately does not manage it), which is
                        // exactly why the read-only page has to surface it.
                        TextEntry::make('visibility_roles')
                            ->label(static::trans('fields.visibility.roles'))
                            ->state(fn (TopbarMenuItem $record): array => static::rolesOf($record))
                            ->badge()
                            // Same list decides both, or a record whose stored
                            // roles are all unusable would show the label over
                            // an empty row.
                            ->visible(fn (TopbarMenuItem $record): bool => static::rolesOf($record) !== []),

                        // Read the stored key the way the edit form fills it —
                        // route items included. The rule only applies to URL
                        // items, so the entry is hidden for route ones rather
                        // than reporting an invented "no" over a stored "yes"
                        // that a type switch would bring back.
                        IconEntry::make('visibility_hide_on_current_domain')
                            ->label(static::trans('fields.hide_on_current_domain.label'))
                            ->visible(fn (TopbarMenuItem $record): bool => $record->type === TopbarMenuItem::TYPE_URL)
                            ->state(fn (TopbarMenuItem $record): bool => ($record->visibility['hide_on_current_domain'] ?? false) === true)
                            ->boolean(),

                        TextEntry::make('sort')
                            ->label(static::trans('fields.sort.label')),

                        IconEntry::make('is_active')
                            ->label(static::trans('fields.is_active.label'))
                            ->boolean(),
                    ]),

                Section::make(static::trans('sections.record'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(static::trans('columns.created_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label(static::trans('columns.updated_at'))
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
            ]);
    }

    /**
     * The order the items table lands on. Filament evaluates this by parameter
     * name, so `$query` must keep its name.
     *
     * @param  Builder<TopbarMenuItem>  $query
     * @return Builder<TopbarMenuItem>
     */
    public static function menuOrder(Builder $query): Builder
    {
        return $query->treeOrdered();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // One visual column, mirroring the topbar exactly: the favicon
                // (Filament renders a slash-containing string as an <img>)
                // takes precedence over the validated Heroicon. displayIcon()
                // is the same helper the topbar view uses, so the table and
                // the rendered menu never disagree.
                //
                // The column's STATE — not just its ->icon() — has to be
                // displayIcon(): IconColumn::toEmbeddedHtml() returns the empty
                // placeholder for a blank state before it ever calls the
                // ->icon() closure. Bound to the `icon` attribute, every item
                // that carries a favicon but no Heroicon name (the common case
                // for external links) therefore rendered as an empty cell.
                IconColumn::make('display_icon')
                    ->label('')
                    ->grow(false)
                    ->state(fn (TopbarMenuItem $record): ?string => $record->displayIcon())
                    ->icon(fn (?string $state): ?string => $state),

                TextColumn::make('label')
                    ->label(static::trans('fields.label'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (TopbarMenuItem $record): ?string => $record->resolveUrl()),

                TextColumn::make('parent.label')
                    ->label(static::trans('columns.parent'))
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(static::trans('columns.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::trans("fields.type.options.{$state}"))
                    ->color(fn (string $state): string => $state === TopbarMenuItem::TYPE_ROUTE ? 'info' : 'gray'),

                // Trust marker for the fleet scenario: flipping the domain
                // toggle makes the item vanish from this very admin's topbar,
                // which reads as a deletion without this badge. State comes
                // from the same isHiddenOnCurrentDomain() the render path
                // uses, so the badge and the menu never disagree. Empty for
                // most rows on purpose — no loud header needed.
                IconColumn::make('hidden_on_current_domain')
                    ->label(static::trans('columns.hidden_on_current_domain'))
                    ->state(fn (TopbarMenuItem $record): bool => $record->isHiddenOnCurrentDomain())
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-eye-slash' : null)
                    ->color('gray')
                    ->tooltip(fn (?bool $state): ?string => $state
                        ? static::trans('columns.hidden_on_current_domain_tooltip')
                        : null)
                    ->toggleable(),

                TextColumn::make('target')
                    ->label(static::trans('columns.target'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => static::trans('fields.target.options.'.($state === TopbarMenuItem::TARGET_BLANK ? 'blank' : 'self')))
                    ->toggleable(isToggledHiddenByDefault: true),

                // `disabled()` is the ONLY gate Filament checks before an
                // inline editable column writes. Its own source says so:
                // "Inline editable columns (ToggleColumn, TextInputColumn,
                // SelectColumn, CheckboxColumn) do not automatically check
                // Laravel Model Policies before saving. Only the disabled()
                // state is checked." Without this, a user the `viewAny` policy
                // admits to the list page could flip any item — on a
                // fleet-shared menu, for every app reading that table.
                //
                // The record is bound before isDisabled() is evaluated
                // (HasColumns::updateTableColumnState), so the closure judges
                // the row actually being written.
                ToggleColumn::make('is_active')
                    ->label(static::trans('columns.is_active'))
                    ->disabled(fn (TopbarMenuItem $record): bool => ! static::can('update', $record)),

                TextColumn::make('sort')
                    ->label(static::trans('columns.sort'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Not `defaultSort('sort')`: that column is a position among
            // siblings, so ordering the flat table by it puts one group's
            // children between another group's roots. treeOrdered() lists the
            // menu the way the topbar renders it. A column header the admin
            // clicks still wins — this is only the order they land on.
            ->defaultSort(static::menuOrder(...))
            ->reorderable('sort')
            // Filament persists reorders with a bulk query-builder UPDATE that
            // fires no Eloquent events, so the model's saved-hook cache flush
            // never runs — flush explicitly here to keep the topbar in sync.
            ->afterReordering(fn () => app(TopbarMenu::class)->flushCache())
            ->filters([
                SelectFilter::make('type')
                    ->label(static::trans('filters.type'))
                    ->options([
                        TopbarMenuItem::TYPE_URL => static::trans('fields.type.options.url'),
                        TopbarMenuItem::TYPE_ROUTE => static::trans('fields.type.options.route'),
                    ]),

                TernaryFilter::make('is_active')
                    ->label(static::trans('filters.is_active')),
            ])
            ->recordActions([
                // No per-row "Fetch favicon" here on purpose: resolving one item
                // is offered where the item is being worked on — the suffix
                // button on the Favicon URL field — and the table keeps only the
                // bulk action, for a selection. A row action would repeat one of
                // them on every row of a page whose job is the overview.

                // Added, not merely hidden, when the page is off: a ViewAction
                // without a view route falls back to a modal, which is not the
                // opt-out an admin asked for by disabling the page.
                ...(static::hasViewPage() ? [ViewAction::make()] : []),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Only links are resolved in bulk. Embedding deliberately
                    // has no bulk action: an embedded icon may be one an admin
                    // uploaded by hand — for a host behind auth that is the ONLY
                    // way to get one — and nothing can re-fetch it, so a single
                    // click that overwrites a selection of them is a door that
                    // only opens one way. It is also the expensive direction (a
                    // page fetch plus up to eleven candidate requests per item,
                    // synchronously, inside the web request). The artisan
                    // command covers the bulk case and skips items that already
                    // carry an icon unless --force says otherwise.
                    // A custom BulkAction carries no authorization of its own —
                    // Filament's own bulk actions declare theirs, and this one
                    // has to as well. It persists `favicon_url` on every
                    // selected record and makes the server fetch third-party
                    // hosts, so it is an `update` even though it does not look
                    // like one in the form. authorizeIndividualRecords also
                    // filters the selection: `$records` reaching the closure is
                    // already the authorized subset (Action::resolveDefaultClosureDependencyForEvaluationByName),
                    // and Filament reports the rest as refused.
                    BulkAction::make('refreshFavicons')
                        ->label(static::trans('actions.fetch_favicons'))
                        ->icon('heroicon-m-arrow-path')
                        ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                        ->authorizeIndividualRecords('update')
                        ->action(fn (Collection $records) => static::resolveFaviconsFor($records))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => Pages\ListTopbarMenuItems::route('/'),
            'create' => Pages\CreateTopbarMenuItem::route('/create'),
        ];

        // Registered after `create` on purpose: `/{record}` would otherwise
        // match `/create` first and swallow the create page. Filament reads
        // this array to decide the post-save redirect and the View action's
        // URL, so the whole feature hangs off this one conditional.
        if (static::hasViewPage()) {
            $pages['view'] = Pages\ViewTopbarMenuItem::route('/{record}');
        }

        $pages['edit'] = Pages\EditTopbarMenuItem::route('/{record}/edit');

        return $pages;
    }
}
