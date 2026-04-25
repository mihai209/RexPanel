<?php

namespace App\Providers;

use App\Services\SystemSettingsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Reddit\RedditExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        View::composer('app', function ($view): void {
            $branding = app(SystemSettingsService::class)->branding();

            config([
                'app.name' => $branding['brandName'],
            ]);

            $view->with('appBranding', $branding);
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            (new DiscordExtendSocialite())->handle($event);
            (new RedditExtendSocialite())->handle($event);
        });
    }
}
