<?php

namespace App\Providers\Filament;

use App\Models\SystemSetting;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => SystemSetting::query()
                ->where('key', 'company.name')
                ->value('value') ?: config('app.name'))
            ->brandLogo(fn (): ?string => SystemSetting::query()
                ->where('key', 'company.logo_path')
                ->value('value') ? route('company.logo') : null)
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('English')
                    ->url(fn (): string => route('lang.switch', ['lang' => 'en']))
                    ->icon('heroicon-o-language')
                    ->sort(0)
                    ->visible(fn (): bool => app()->getLocale() === 'ar'),
                NavigationItem::make('العربية')
                    ->url(fn (): string => route('lang.switch', ['lang' => 'ar']))
                    ->icon('heroicon-o-language')
                    ->sort(0)
                    ->visible(fn (): bool => app()->getLocale() === 'en'),
                NavigationItem::make('نقطة البيع')
                    ->url('/pos')
                    ->icon('heroicon-o-shopping-cart')
                    ->sort(1)
                    ->visible(fn (): bool => auth()->user()?->can('sales.view') ?? false),
                NavigationItem::make('إعدادات نقطة البيع')
                    ->url('/pos/settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->sort(2)
                    ->visible(fn (): bool => auth()->user()?->can('sales.settings') ?? false),
                NavigationItem::make('الخزائن والحركات المالية')
                    ->url('/admin/treasuries')
                    ->icon('heroicon-o-banknotes')
                    ->sort(20)
                    ->visible(fn (): bool => auth()->user()?->can('treasuries.view') ?? false),
                NavigationItem::make('حركات المخزون')
                    ->url('/admin/stock-movements')
                    ->icon('heroicon-o-arrows-right-left')
                    ->sort(21)
                    ->visible(fn (): bool => auth()->user()?->can('stock-movements.view') ?? false),
                NavigationItem::make('الجرد')
                    ->url('/admin/stocktakes')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->sort(22)
                    ->visible(fn (): bool => auth()->user()?->can('stocktakes.view') ?? false),
                NavigationItem::make('التقارير المالية')
                    ->url('/admin/financial-reports')
                    ->icon('heroicon-o-chart-pie')
                    ->sort(23)
                    ->visible(fn (): bool => auth()->user()?->can('finance.view') ?? false),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
