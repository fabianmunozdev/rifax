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
        // El panel está en español: las etiquetas que Filament genera desde el nombre
        // del campo ("Created at") se traducen con lang/es.json.
        foreach ([
            \Filament\Forms\Components\Field::class,
            \Filament\Tables\Columns\Column::class,
            \Filament\Tables\Filters\BaseFilter::class,
            \Filament\Infolists\Components\Entry::class,
            \Filament\Actions\Action::class,
        ] as $component) {
            $component::configureUsing(fn ($instance) => $instance->translateLabel());
        }
    }
}
