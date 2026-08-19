<?php

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\AppGlobalSearchProvider;
use App\Filament\Pages\MyProfile;
use App\Livewire\Auth\CustomLogin;
use App\Livewire\Auth\CustomRegister;
use App\Livewire\Auth\CustomRequestPasswordReset;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
                        // ->login()
            ->login(CustomLogin::class)
            ->registration(CustomRegister::class)
                       // ->passwordResetRequest(CustomRequestPasswordReset::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(AppGlobalSearchProvider::class)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="'.asset('css/filament-admin-theme.css').'?v='.filemtime(public_path('css/filament-admin-theme.css')).'">'
                    .'<link rel="manifest" href="'.asset('manifest.webmanifest').'">'
                    .'<meta name="theme-color" content="#1C3A8A">'
                    .'<link rel="apple-touch-icon" href="'.asset('icons/admin-icon-192.png').'">'
                    .'<meta name="apple-mobile-web-app-capable" content="yes">'
                    .'<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
                    .'<meta name="apple-mobile-web-app-title" content="MNCH Admin">'
                ),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(
                    '<script>'
                    ."if ('serviceWorker' in navigator) {"
                    ."  window.addEventListener('load', function () {"
                    ."    navigator.serviceWorker.register('".asset('sw.js')."', { scope: '/admin/' }).catch(function () {});"
                    .'  });'
                    .'}'
                    .'</script>'
                ),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn () => view('filament.components.user-menu-header'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_AFTER,
                fn () => view('filament.components.install-app-menu-item'),
            )
            ->navigationGroups([
                'Dashboards',
                'Rubric Assessments',
                'Facility Assessment',
                'Training Management',
                'Indicator Catalog',
                'knowledge Base',
                'Reporting',
                'Curriculum',
                'Organization Units',
                'App Configuration',
                'Inventory',
                'Report Management',
                'Reports & Analytics',
                'System Administration',
            ])
            ->navigationItems([
                NavigationItem::make('Newborn Care')
                    ->group('Rubric Assessments')
                    ->icon('heroicon-o-heart')
                    ->url('/admin/mentor-dashboard?program=newborn')
                    ->sort(1)
                    ->visible(false),
                NavigationItem::make('Infant and Child Care')
                    ->group('Rubric Assessments')
                    ->icon('heroicon-o-user-group')
                    ->url('/admin/mentor-dashboard?program=infant')
                    ->sort(2)
                    ->visible(false),
                NavigationItem::make('Maternal Health (EmONC)')
                    ->group('Rubric Assessments')
                    ->icon('heroicon-o-heart')
                    ->url('/admin/mentor-dashboard?program=emonc')
                    ->sort(3)
                    ->visible(false),
            ])
            ->profile(MyProfile::class)
            ->userMenuItems([
                MenuItem::make()
                    ->label('Home')
                    ->icon('heroicon-o-home')
                    ->url('/'),
                MenuItem::make()
                    ->label('Analytics Dashboard')
                    ->icon('heroicon-o-chart-bar')
                    ->url('/analytics/dashboard?mode=assessment'),
                'profile' => MenuItem::make()
                    ->label('My Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url('/admin/my-profile'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
                        /* ->widgets([
                          Widgets\AccountWidget::class,
                          Widgets\FilamentInfoWidget::class,
                          ]) */
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->homeUrl(function () {
                if (! auth()->check()) {
                    return '/admin';
                }
                $user = auth()->user();
                if ($user->hasRole('mentee')) {
                    return '/admin/mentee-dashboard';
                }
                if ($user->hasRole('head_drmh')) {
                    return '/admin/head-drmh-dashboard';
                }

                return '/admin';
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private static function canSeeMentorshipsNav(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Mentees only see mentorship nav when explicitly granted
        if ($user->hasRole('mentee')) {
            return $user->canCreateMentorships();
        }

        // Anyone with mentorship training access sees the nav
        return $user->can('view_any_mentorship::training');
    }
}
