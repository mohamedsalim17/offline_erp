<?php

namespace App\Filament\Pages;

use App\Models\Backup;
use App\Models\SystemSetting as SystemSettingModel;
use Filament\Pages\Page;

class SystemSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'إعدادات النظام';

    protected static ?string $title = 'إعدادات النظام';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.system-settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('system-settings.view') ?? false;
    }

    protected function getViewData(): array
    {
        $settings = SystemSettingModel::query()
            ->whereIn('key', [
                'company.name',
                'company.address',
                'company.phone',
                'company.email',
                'company.logo_path',
                'system.locale',
            ])
            ->pluck('value', 'key')
            ->all();

        return [
            'settings' => [
                'company_name' => $settings['company.name'] ?? config('app.name'),
                'company_address' => $settings['company.address'] ?? '',
                'company_phone' => $settings['company.phone'] ?? '',
                'company_email' => $settings['company.email'] ?? '',
                'company_logo_path' => $settings['company.logo_path'] ?? null,
                'locale' => $settings['system.locale'] ?? config('app.locale'),
            ],
            'backups' => Backup::query()->latest()->limit(20)->get(),
            'canUpdateSettings' => auth()->user()?->can('system-settings.update') ?? false,
            'canViewBackups' => auth()->user()?->can('backups.view') ?? false,
            'canCreateBackups' => auth()->user()?->can('backups.create') ?? false,
            'canUploadBackups' => auth()->user()?->can('backups.upload') ?? false,
            'canRestoreBackups' => auth()->user()?->can('backups.restore') ?? false,
            'canDeleteBackups' => auth()->user()?->can('backups.delete') ?? false,
            'canManagePosSettings' => auth()->user()?->can('sales.settings') ?? false,
            'canViewReports' => auth()->user()?->can('reports.view') ?? false,
        ];
    }
}
