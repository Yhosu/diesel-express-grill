<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class YhosuPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('DIESEL NACIONAL')
            ->brandLogo(asset('images/logo-diesel-gold.svg'))
            ->darkModeBrandLogo(asset('images/logo-diesel-gold.svg'))
            ->brandLogoHeight('3rem')
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode(true, isForced: true)
            ->font('Poppins')
            ->spa()
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::hex('#C19D60'),
                'danger' => Color::Rose,
                'gray' => Color::Zinc,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::hex('#C19D60'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('
                    <style>
                        :root {
                            --dn-gold: #C19D60;
                            --dn-dark-bg: #212121;
                            --dn-dark-panel: #292929;
                            --dn-dark-card: #303030;
                        }
                        body.fi-body {
                            background-color: #212121 !important;
                            color: rgba(255, 255, 255, 0.9);
                        }
                        .fi-sidebar, .fi-topbar nav {
                            background-color: #292929 !important;
                            border-color: rgba(193, 157, 96, 0.22) !important;
                        }
                        .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat {
                            background-color: #292929 !important;
                            border-color: rgba(193, 157, 96, 0.20) !important;
                        }
                        .fi-sidebar-item-active > a {
                            background-color: rgba(193, 157, 96, 0.16) !important;
                            border-left: 3px solid #C19D60;
                        }
                        .fi-header-heading {
                            letter-spacing: 0.02em;
                        }
                    </style>
                ')
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function (): HtmlString {
                    $user = Auth::user();
                    if (! $user instanceof User) {
                        return new HtmlString('');
                    }
                    $label = $user->isSuperAdmin()
                        ? 'ROL: SUPER ADMINISTRADOR'
                        : 'ROL: SUBADMINISTRADOR (SOLO REGISTRO)';

                    return new HtmlString(
                        '<div class="hidden md:flex items-center gap-2 mr-2 px-3 py-1 rounded-full border border-[#C19D60]/50 bg-[#212121] text-xs font-mono font-bold tracking-wider text-[#C19D60]">'
                        . e($label) .
                        '</div>'
                    );
                }
            )
            ->navigationGroups([
                NavigationGroup::make('1. Operación Diaria Móvil'),
                NavigationGroup::make('2. Costos, Recetas BOM y Compras'),
                NavigationGroup::make('3. Reportes y Administración'),
                NavigationGroup::make('4. Configuración'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->databaseNotifications()
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
