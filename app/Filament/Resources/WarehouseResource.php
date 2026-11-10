<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('warehouses.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('warehouses.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('warehouses.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('warehouses.delete') ?? false)
            && ! $record->children()->exists()
            && ! $record->products()->exists()
            && ! $record->sales()->exists()
            && ! $record->stocktakes()->exists();
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    public static function getNavigationLabel(): string
    {
        return __('المخازن والمستودعات');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المخازن والمتفرعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                \Filament\Forms\Components\TextInput::make('name')
                    ->label(__('اسم المخزن'))
                    ->required(),

                \Filament\Forms\Components\Select::make('parent_id')
                    ->label(__('المخزن الأب (اختياري، يدعم مستويات متداخلة)'))
                    ->placeholder(__('اترك الحقل فارغاً للمخزن الأعلى'))
                    ->options(fn (?Warehouse $record): array => Warehouse::query()
                        ->when($record, fn ($query) => $query->where('id', '<>', $record->id))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),

                \Filament\Forms\Components\TextInput::make('location')
                    ->label(__('موقع المخزن المادي / الرف')),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label(__('حالة المخزن نشط'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم المخزن'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label(__('المخزن الأب'))
                    ->placeholder('---')
                    ->badge()
                    ->color('slate'),

                TextColumn::make('location')
                    ->label(__('الموقع')),

                IconColumn::make('is_active')
                    ->label(__('نشط'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label(__('المخزن الرئيسي'))
                    ->options(Warehouse::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn (Warehouse $record): bool => ! $record->children()->exists()
                        && ! $record->products()->exists()
                        && ! $record->sales()->exists()
                        && ! $record->stocktakes()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWarehouses::route('/'),
        ];
    }
}
