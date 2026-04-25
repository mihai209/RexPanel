<?php

use App\Http\Controllers\Web\OAuthController;
use App\Services\SystemSettingsService;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

Route::get('/{any?}', function () {
    return view('app', [
        'appBranding' => app(SystemSettingsService::class)->branding(),
    ]);
})->where('any', '^(?!api).*$');
