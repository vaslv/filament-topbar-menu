<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
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

                        TextInput::make('sort')
                            ->label(static::trans('fields.sort.label'))
                            ->numeric()
                            ->default(0)
                            ->helperText(static::trans('fields.sort.helper')),

                        Toggle::make('is_active')
                            ->label(static::trans('fields.is_active.label'))
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('favicon_url')
                    ->label('')
                    ->imageSize(16)
                    ->grow(false),

                // Mirror the topbar: a favicon takes precedence over the icon,
                // so this column only renders the (validated) Heroicon when no
                // favicon is set. safeIconName() guards against unknown names.
                IconColumn::make('icon')
                    ->label('')
                    ->grow(false)
                    ->icon(fn (TopbarMenuItem $record): ?string => $record->favicon_url
                        ? null
                        : TopbarMenuItem::safeIconName($record->icon)),

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

                TextColumn::make('target')
                    ->label(static::trans('columns.target'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => static::trans('fields.target.options.'.($state === TopbarMenuItem::TARGET_BLANK ? 'blank' : 'self')))
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label(static::trans('columns.is_active')),

                TextColumn::make('sort')
                    ->label(static::trans('columns.sort'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort')
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
                Action::make('refreshFavicon')
                    ->label(static::trans('actions.fetch_favicon'))
                    ->icon('heroicon-m-arrow-path')
                    ->visible(
                        fn (TopbarMenuItem $record): bool => config('filament-topbar-menu.enable_favicons', true)
                            && $record->type === TopbarMenuItem::TYPE_URL
                            && filled($record->url),
                    )
                    ->action(function (TopbarMenuItem $record): void {
                        $favicon = app(FaviconResolver::class)->resolve($record->url);

                        if ($favicon === null) {
                            Notification::make()
                                ->title(static::trans('notifications.favicon_not_found'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->forceFill(['favicon_url' => $favicon])->save();

                        Notification::make()
                            ->title(static::trans('notifications.favicon_updated'))
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('refreshFavicons')
                        ->label(static::trans('actions.fetch_favicons'))
                        ->icon('heroicon-m-arrow-path')
                        ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                        ->action(function (Collection $records): void {
                            $resolver = app(FaviconResolver::class);
                            $resolved = 0;

                            /** @var TopbarMenuItem $record */
                            foreach ($records as $record) {
                                if ($record->type !== TopbarMenuItem::TYPE_URL || blank($record->url)) {
                                    continue;
                                }

                                if ($favicon = $resolver->resolve($record->url)) {
                                    $record->forceFill(['favicon_url' => $favicon])->save();
                                    $resolved++;
                                }
                            }

                            Notification::make()
                                ->title(static::trans('notifications.favicons_resolved', ['count' => $resolved]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTopbarMenuItems::route('/'),
            'create' => Pages\CreateTopbarMenuItem::route('/create'),
            'edit' => Pages\EditTopbarMenuItem::route('/{record}/edit'),
        ];
    }
}
