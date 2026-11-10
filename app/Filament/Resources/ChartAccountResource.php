<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartAccountResource\Pages;
use App\Models\ChartAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChartAccountResource extends Resource
{
    protected static ?string $model = ChartAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'شجرة الحسابات';

    protected static ?string $modelLabel = 'حساب';

    protected static ?string $pluralModelLabel = 'شجرة الحسابات';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('finance.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('finance.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('finance.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('finance.manage') ?? false)
            && ! $record->children()->exists()
            && ! $record->journalLines()->exists()
            && ! $record->treasuries()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('رمز الحساب')->required()->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('name')->label('اسم الحساب')->required()->maxLength(120),
            Select::make('type')->label('طبيعة الحساب')->required()->options([
                'asset' => 'أصل',
                'liability' => 'التزام',
                'equity' => 'حقوق ملكية',
                'revenue' => 'إيراد',
                'expense' => 'مصروف',
            ]),
            Select::make('parent_id')
                ->label('الحساب الأب')
                ->options(fn (?ChartAccount $record): array => ChartAccount::query()
                    ->when($record, fn ($query) => $query->where('id', '<>', $record->id))
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->mapWithKeys(fn (ChartAccount $account) => [$account->id => "{$account->code} — {$account->name}"])
                    ->all())
                ->searchable(),
            TextInput::make('system_key')->label('مفتاح النظام (للحسابات المعرفة مسبقاً)')->disabled(),
            Toggle::make('is_group')->label('حساب تجميعي (لا يستقبل قيوداً)')->default(false),
            Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name')->label('الحساب')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('الحساب الأب')->placeholder('—'),
                TextColumn::make('type')->label('النوع')->badge(),
                IconColumn::make('is_group')->label('تجميعي')->boolean(),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->defaultSort('code')
            ->actions([
                \Filament\Actions\EditAction::make()->label('تعديل'),
                \Filament\Actions\DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChartAccounts::route('/')];
    }
}
