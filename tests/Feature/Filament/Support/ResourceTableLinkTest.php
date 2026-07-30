<?php

namespace Tests\Feature\Filament\Support;

use App\Filament\Support\ResourceTableLink;
use Tests\TestCase;

class ResourceTableLinkTest extends TestCase
{
    public function test_it_builds_ticket_index_urls_with_preapplied_filters(): void
    {
        $url = ResourceTableLink::tickets([
            'raffle_id' => ResourceTableLink::value(42),
            'awaiting_delivery' => ResourceTableLink::toggle(),
        ]);

        $this->assertStringContainsString('/admin/tickets', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('42', $query['tableFilters']['raffle_id']['value']);
        $this->assertSame('1', $query['tableFilters']['awaiting_delivery']['isActive']);
    }

    public function test_it_builds_whatsapp_index_urls_with_preapplied_filters(): void
    {
        $url = ResourceTableLink::whatsappMessages([
            'winner_notifications' => ResourceTableLink::toggle(),
            'pending_outbound' => ResourceTableLink::toggle(),
        ]);

        $this->assertStringContainsString('/admin/whatsapp-messages', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('1', $query['tableFilters']['winner_notifications']['isActive']);
        $this->assertSame('1', $query['tableFilters']['pending_outbound']['isActive']);
    }
}
