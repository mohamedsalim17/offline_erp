<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('products.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('products.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('products.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('products.delete') ?? false)
            && ! $record->warehouses()->exists()
            && ! $record->saleItems()->exists()
            && ! $record->stockMovementItems()->exists();
    }

    protected static ?string $navigationLabel = 'إدارة المنتجات';
    protected static ?string $pluralModelLabel = 'المنتجات';
    protected static ?string $modelLabel = 'منتج';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect([
                'current_stock' => DB::table('product_warehouse')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('product_warehouse.product_id', 'products.id'),
            ]);
    }

    // التوافقية المطلقة لـ Filament 5: استخدام كائن الـ Schema لمنع خطأ التحقق تماماً
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // القسم الأول: البيانات الأساسية للمنتج والباركوود
            Section::make('البيانات الأساسية وقارئ الباركود')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('اسم المنتج')
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('barcode')
                        ->label('رقم الباركود (اتركه فارغاً للتوليد التلقائي)')
                        ->nullable()
                        ->unique(ignoreRecord: true),

                    \Filament\Forms\Components\TextInput::make('sku')
                        ->label('الرمز المخزني (اتركه فارغاً للتوليد التلقائي)')
                        ->nullable()
                        ->unique(ignoreRecord: true),

                    \Filament\Forms\Components\TextInput::make('unit_name')
                        ->label('وحدة القياس')
                        ->default('قطعة')
                        ->required()
                        ->maxLength(40)
                        ->hint('تُحدّث الكمية من حركات المخزون المعتمدة لكل مخزن.'),

                    \Filament\Forms\Components\Select::make('category_id')
                        ->label('قسم المنتج التابع له')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable(),
                ])->columns(2),

            // القسم الثاني: التسعير المتقدم وحسابات الأرباح والتجزئة
            Section::make('التسعير وحساب التكلفة والتجزئة')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('cost_price')
                        ->label('سعر التكلفة / الشراء')
                        ->numeric()
                        ->required()
                        ->prefix('IQD'),

                    \Filament\Forms\Components\TextInput::make('selling_price')
                        ->label('سعر بيع القطاعي (التجزئة)')
                        ->numeric()
                        ->required()
                        ->prefix('IQD'),

                    \Filament\Forms\Components\TextInput::make('wholesale_price')
                        ->label('سعر بيع الجملة')
                        ->numeric()
                        ->prefix('IQD'),

                    \Filament\Forms\Components\TextInput::make('minimum_stock_alert')
                        ->label('حد الطلب والتنبيه (الحد الأدنى)')
                        ->numeric()
                        ->default(5)
                        ->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('اسم المنتج')->searchable()->sortable(),
                TextColumn::make('barcode')->label('الباركود')->searchable(),
                TextColumn::make('sku')->label('الرمز المخزني')->searchable(),
                TextColumn::make('category.name')->label('القسم')->sortable(),
                TextColumn::make('current_stock')
                    ->label('الكمية بالمخازن')
                    ->formatStateUsing(fn ($state, Product $record): string => number_format((int) $state).' '.$record->unit_name)
                    ->sortable(),
                TextColumn::make('cost_price')->label('التكلفة')->money('IQD')->sortable(),
                TextColumn::make('selling_price')->label('القطاعي')->money('IQD')->sortable(),
                TextColumn::make('wholesale_price')->label('الجملة')->money('IQD')->sortable(),
                TextColumn::make('minimum_stock_alert')->label('حد التنبيه')->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('category_id')
                    ->label('تصفية حسب القسم')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()->label('تعديل'),
                \Filament\Actions\DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProducts::route('/'),
        ];
    }
}
