<?php

namespace App\Filament\Support;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;

class ResourceTableLink
{
    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    public static function tickets(array $filters = []): string
    {
        return TicketResource::getUrl('index', [
            'tableFilters' => $filters,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    public static function whatsappMessages(array $filters = []): string
    {
        return WhatsappMessageResource::getUrl('index', [
            'tableFilters' => $filters,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    public static function payments(array $filters = []): string
    {
        return PaymentResource::getUrl('index', [
            'tableFilters' => $filters,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    public static function purchases(array $filters = []): string
    {
        return PurchaseResource::getUrl('index', [
            'tableFilters' => $filters,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    public static function raffles(array $filters = []): string
    {
        return RaffleResource::getUrl('index', [
            'tableFilters' => $filters,
        ]);
    }

    /**
     * @return array{isActive: true}
     */
    public static function toggle(): array
    {
        return ['isActive' => true];
    }

    /**
     * @return array{value: mixed}
     */
    public static function value(mixed $value): array
    {
        return ['value' => $value];
    }
}
