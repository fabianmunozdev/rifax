<?php

namespace Tests\Feature\Filament\Customers;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\ConversationState;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_actions_use_resource_urls(): void
    {
        $customer = Customer::factory()->create();
        $conversation = ConversationState::factory()->for($customer)->create();

        $customer->load('currentConversationState');

        $this->assertSame(
            ConversationResource::getUrl('view', ['record' => $conversation]),
            CustomerResource::makeOpenConversationAction()->record($customer)->getUrl(),
        );

        $this->assertSame(
            PurchaseResource::getUrl('index', ['tableSearch' => $customer->phone]),
            CustomerResource::makeOpenPurchasesAction()->record($customer)->getUrl(),
        );

        $this->assertSame(
            WhatsappMessageResource::getUrl('index', ['tableSearch' => $customer->phone]),
            CustomerResource::makeOpenWhatsappMessagesAction()->record($customer)->getUrl(),
        );
    }
}
