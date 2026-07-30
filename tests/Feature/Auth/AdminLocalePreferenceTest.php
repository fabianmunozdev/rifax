<?php

namespace Tests\Feature\Auth;

use App\Enums\PanelRole;
use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\OperationsUi;
use App\Filament\Widgets\FinanceReviewHealthWidget;
use App\Filament\Widgets\RaffleWinnerNotificationHealthWidget;
use App\Filament\Widgets\WhatsappChannelHealthWidget;
use App\Http\Middleware\SetAdminLocale;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminLocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_authenticated_user_preferred_admin_locale(): void
    {
        $user = User::factory()->admin()->create([
            'preferred_locale' => 'en',
        ]);

        $response = $this->actingAs($user)
            ->from('/admin')
            ->post(route('admin.locale.update'), [
                'locale' => 'es',
            ]);

        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_locale' => 'es',
        ]);
        $this->assertSame('es', session('admin_locale'));
    }

    public function test_set_admin_locale_middleware_uses_the_authenticated_user_preference(): void
    {
        Route::middleware(SetAdminLocale::class)->get('/_test/admin-locale', fn (): string => app()->getLocale());

        $user = User::factory()->admin()->create([
            'preferred_locale' => 'es',
        ]);

        $this->actingAs($user)
            ->get('/_test/admin-locale')
            ->assertOk()
            ->assertSee('es');
    }

    public function test_set_admin_locale_middleware_falls_back_to_company_default_locale(): void
    {
        Route::middleware(SetAdminLocale::class)->get('/_test/admin-locale-fallback', fn (): string => app()->getLocale());

        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'support_phone' => '+573001234567',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'logo_path' => null,
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Ayuda base.',
            'terms_url' => null,
            'privacy_policy_url' => null,
        ]);

        $this->get('/_test/admin-locale-fallback')
            ->assertOk()
            ->assertSee('es');
    }

    public function test_panel_role_labels_and_resource_labels_follow_the_active_locale(): void
    {
        app()->setLocale('es');

        $this->assertSame('Administrador', PanelRole::Admin->label());
        $this->assertSame('Configuracion de Empresa', CompanySettingResource::getNavigationLabel());
        $this->assertSame('Rifas', RaffleResource::getNavigationLabel());
        $this->assertSame('Pagos', PaymentResource::getNavigationLabel());
        $this->assertSame('Compras', PurchaseResource::getNavigationLabel());
        $this->assertSame('Conversaciones', ConversationResource::getNavigationLabel());
        $this->assertSame('Clientes', CustomerResource::getNavigationLabel());
        $this->assertSame('Boletos', TicketResource::getNavigationLabel());
        $this->assertSame('Mensajes de WhatsApp', WhatsappMessageResource::getNavigationLabel());
        $this->assertSame('En revision', OperationsUi::paymentStatusLabel('pending_review'));
        $this->assertSame('Pago enviado', OperationsUi::purchaseStatusLabel('payment_submitted'));
        $this->assertSame('Entregado', OperationsUi::whatsappProviderStatusLabel('delivered'));
        $this->assertSame('Recordatorio de pago', OperationsUi::whatsappIntentLabel('purchase_payment_reminder'));
        $this->assertSame('Menu principal', OperationsUi::conversationStatusLabel('main_menu'));
        $this->assertSame('Salud de revision financiera', $this->widgetHeading(FinanceReviewHealthWidget::class));
        $this->assertSame('Salud del canal de WhatsApp', $this->widgetHeading(WhatsappChannelHealthWidget::class));
        $this->assertSame('Salud de notificaciones de ganador', $this->widgetHeading(RaffleWinnerNotificationHealthWidget::class));

        app()->setLocale('en');

        $this->assertSame('Admin', PanelRole::Admin->label());
        $this->assertSame('Company Settings', CompanySettingResource::getNavigationLabel());
        $this->assertSame('Raffles', RaffleResource::getNavigationLabel());
        $this->assertSame('Payments', PaymentResource::getNavigationLabel());
        $this->assertSame('Purchases', PurchaseResource::getNavigationLabel());
        $this->assertSame('Conversations', ConversationResource::getNavigationLabel());
        $this->assertSame('Customers', CustomerResource::getNavigationLabel());
        $this->assertSame('Tickets', TicketResource::getNavigationLabel());
        $this->assertSame('WhatsApp Messages', WhatsappMessageResource::getNavigationLabel());
        $this->assertSame('Under review', OperationsUi::paymentStatusLabel('pending_review'));
        $this->assertSame('Payment submitted', OperationsUi::purchaseStatusLabel('payment_submitted'));
        $this->assertSame('Delivered', OperationsUi::whatsappProviderStatusLabel('delivered'));
        $this->assertSame('Payment reminder', OperationsUi::whatsappIntentLabel('purchase_payment_reminder'));
        $this->assertSame('Main menu', OperationsUi::conversationStatusLabel('main_menu'));
        $this->assertSame('Finance review health', $this->widgetHeading(FinanceReviewHealthWidget::class));
        $this->assertSame('WhatsApp channel health', $this->widgetHeading(WhatsappChannelHealthWidget::class));
        $this->assertSame('Winner notification health', $this->widgetHeading(RaffleWinnerNotificationHealthWidget::class));
    }

    private function widgetHeading(string $widgetClass): ?string
    {
        $widget = new $widgetClass();
        $method = new \ReflectionMethod($widget, 'getHeading');
        $method->setAccessible(true);

        /** @var ?string $heading */
        $heading = $method->invoke($widget);

        return $heading;
    }
}
