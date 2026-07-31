<?php

namespace App\Providers\Filament;

use App\Livewire\Auth\CustomLogin;
use App\Livewire\Auth\CustomRegister;
use App\Livewire\Auth\CustomRequestPasswordReset;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Enums\ThemeMode;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use App\Filament\Pages\MyProfile;
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
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="'.asset('css/filament-admin-theme.css').'?v='.filemtime(public_path('css/filament-admin-theme.css')).'">'
                ),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn () => view('filament.components.user-menu-header'),
            )
            ->navigationGroups([
                'Dashboards',
                'Mentorships',
                'Facility Assessment',
                'Training Management',
                'Indicator Catalog',
                'knowledge Base',
                'Reporting',
                'Curriculum',
                'Organization Units',
                'Inventory',
                'Report Management',
                'Reports & Analytics',
            ])
            ->navigationItems([
                NavigationItem::make('Newborn Care')
                    ->group('Mentorships')
                    ->icon('heroicon-o-heart')
                    ->url('/admin/mentor-dashboard?program=newborn')
                    ->sort(1)
                    ->visible(false),
                NavigationItem::make('Infant and Child Care')
                    ->group('Mentorships')
                    ->icon('heroicon-o-user-group')
                    ->url('/admin/mentor-dashboard?program=infant')
                    ->sort(2)
                    ->visible(false),
                NavigationItem::make('Maternal Health (EmONC)')
                    ->group('Mentorships')
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
