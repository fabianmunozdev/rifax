<?php

namespace App\Providers;

use App\Services\WhatsApp\MetaWhatsappProvider;
use App\Services\WhatsApp\TwilioWhatsappProvider;
use App\Services\WhatsApp\WhatsappProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsappProvider::class, fn ($app) => match (config('services.whatsapp.provider', 'meta')) {
            'twilio' => $app->make(TwilioWhatsappProvider::class),
            default => $app->make(MetaWhatsappProvider::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
