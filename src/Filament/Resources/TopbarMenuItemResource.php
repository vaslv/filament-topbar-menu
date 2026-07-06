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

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $modelLabel = 'Topbar Menu Item';

    protected static ?string $pluralModelLabel = 'Topbar Menu';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return static::plugin()?->getResourceNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getResourceNavigationSort() ?? parent::getNavigationSort();
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
                Section::make('Link')
                    ->columns(2)
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255),

                        Select::make('parent_id')
                            ->label('Parent item')
                            ->placeholder('None (top level)')
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
                            ->helperText('Items with a parent are shown in the parent\'s dropdown.'),

                        Select::make('type')
                            ->label('Link type')
                            ->options([
                                TopbarMenuItem::TYPE_URL => 'URL',
                                TopbarMenuItem::TYPE_ROUTE => 'Laravel route',
                            ])
                            ->default(TopbarMenuItem::TYPE_URL)
                            ->required()
                            ->live(),

                        Select::make('target')
                            ->options([
                                TopbarMenuItem::TARGET_SELF => 'Same tab',
                                TopbarMenuItem::TARGET_BLANK => 'New tab',
                            ])
                            ->default(TopbarMenuItem::TARGET_SELF)
                            ->required(),

                        TextInput::make('url')
                            ->label('URL')
                            ->placeholder('https://example.com')
                            ->url()
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_URL)
                            ->required(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_URL)
                            ->columnSpanFull(),

                        Select::make('route')
                            ->label('Route name')
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
                            ->required(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_ROUTE),

                        KeyValue::make('route_parameters')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->visible(fn (Get $get): bool => $get('type') === TopbarMenuItem::TYPE_ROUTE)
                            ->nullable(),
                    ]),

                Section::make('Appearance & behavior')
                    ->columns(2)
                    ->schema([
                        TextInput::make('icon')
                            ->placeholder('heroicon-o-link')
                            ->helperText('Any icon name supported by Filament, e.g. a Heroicon.'),

                        TextInput::make('favicon_url')
                            ->label('Favicon URL')
                            ->url()
                            ->suffixAction(
                                Action::make('fetchFavicon')
                                    ->label('Fetch favicon')
                                    ->icon('heroicon-m-arrow-path')
                                    ->tooltip('Resolve the favicon from the URL above')
                                    ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                                    ->action(function (Get $get, Set $set): void {
                                        $url = $get('url');

                                        if (blank($url)) {
                                            Notification::make()
                                                ->title('Enter an external URL first')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $favicon = app(FaviconResolver::class)->resolve($url);

                                        if ($favicon === null) {
                                            Notification::make()
                                                ->title('No favicon found')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $set('favicon_url', $favicon);

                                        Notification::make()
                                            ->title('Favicon resolved')
                                            ->success()
                                            ->send();
                                    }),
                            ),

                        Select::make('visibility')
                            ->label('Visible to')
                            ->placeholder('Everyone')
                            ->options([
                                'auth' => 'Authenticated users only',
                                'guest' => 'Guests only',
                            ])
                            ->nullable()
                            ->helperText('Role-based visibility (the "roles" key) is preserved when set directly on the record.')
                            ->formatStateUsing(fn ($state): ?string => TopbarMenuItem::visibilityModeFromArray(is_array($state) ? $state : null))
                            ->dehydrateStateUsing(fn ($state, ?TopbarMenuItem $record): ?array => TopbarMenuItem::applyVisibilityMode($record?->visibility, $state)),

                        TextInput::make('sort')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower values are shown first. You can also drag rows in the list.'),

                        Toggle::make('is_active')
                            ->label('Active')
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

                TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TopbarMenuItem $record): ?string => $record->resolveUrl()),

                TextColumn::make('parent.label')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === TopbarMenuItem::TYPE_ROUTE ? 'info' : 'gray'),

                TextColumn::make('target')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('sort')
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
                    ->options([
                        TopbarMenuItem::TYPE_URL => 'URL',
                        TopbarMenuItem::TYPE_ROUTE => 'Laravel route',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Action::make('refreshFavicon')
                    ->label('Fetch favicon')
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
                                ->title('No favicon found')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->forceFill(['favicon_url' => $favicon])->save();

                        Notification::make()
                            ->title('Favicon updated')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('refreshFavicons')
                        ->label('Fetch favicons')
                        ->icon('heroicon-m-arrow-path')
                        ->visible(fn (): bool => (bool) config('filament-topbar-menu.enable_favicons', true))
                        ->action(function (Collection $records): void {
                            $resolver = app(FaviconResolver::class);
                            $resolved = 0;

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
                                ->title("Resolved {$resolved} favicon(s)")
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
