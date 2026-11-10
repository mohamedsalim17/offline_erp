<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('categories.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('categories.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('categories.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('categories.delete') ?? false)
            && ! $record->products()->exists();
    }

    protected static ?string $navigationLabel = 'أقسام المنتجات';
    protected static ?string $pluralModelLabel = 'الأقسام';
    protected static ?string $modelLabel = 'قسم';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('name')
                ->label('اسم القسم')
                ->required()
                ->maxLength(255)
                ->placeholder('مثال: مواد غذائية، منظفات...'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم القسم')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->actions([
                // استخدام مسار الأفعال المركزي الصحيح والمباشر لـ Filament 5 لحل المشكلة نهائياً
                \Filament\Actions\EditAction::make()->label('تعديل'),
                \Filament\Actions\DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // الاعتماد على ملفك الوحيد الحالي
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
