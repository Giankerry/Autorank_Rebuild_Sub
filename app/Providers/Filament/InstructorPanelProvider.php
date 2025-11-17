<?php

namespace App\Providers\Filament;

use App\Filament\Traits\AppliesCommonPanelBranding;
use App\Filament\Instructor\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use App\Filament\Traits\ManagesPanelColors;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Instructor\Widgets\WelcomeWidget;
use App\Filament\Instructor\Widgets\ScoreSummary;
use App\Filament\Pages\GoogleSettings;

class InstructorPanelProvider extends PanelProvider
{
    use AppliesCommonPanelBranding;
    use ManagesPanelColors;

    public function panel(Panel $panel): Panel
    {
        $panel = $this->applySharedBranding($panel);

        return $panel
            ->id('instructor')
            ->path('instructor')
            ->login(Login::class)
            ->colors($this->getPanelColors())
            ->font('Archivo')
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->discoverResources(in: app_path('Filament/Instructor/Resources'), for: 'App\\Filament\\Instructor\\Resources')
            ->discoverPages(in: app_path('Filament/Instructor/Pages'), for: 'App\\Filament\\Instructor\\Pages')
            ->pages([
                Pages\Dashboard::class,
                GoogleSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Instructor/Widgets'), for: 'App\\Filament\\Instructor\\Widgets')
            ->widgets([
                WelcomeWidget::class,
                ScoreSummary::class,
            ])
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
