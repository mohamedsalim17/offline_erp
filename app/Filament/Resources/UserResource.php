<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'إدارة المستخدمين';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('كلمة المرور')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('roles')
                ->label('الأدوار')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge(),
                TextColumn::make('login_count')
                    ->label('عدد مرات الدخول')
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('لم يسجل الدخول بعد')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()->label('تعديل'),
                \Filament\Actions\DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
