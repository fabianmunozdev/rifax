<?php

namespace App\Filament\Support;

class OperationsUi
{
    public static function purchaseStatusLabel(?string $state): string
    {
        return match ($state) {
            'paid' => __('admin.operations.purchase_statuses.paid'),
            'payment_submitted' => __('admin.operations.purchase_statuses.payment_submitted'),
            'under_review' => __('admin.operations.purchase_statuses.under_review'),
            'reserved' => __('admin.operations.purchase_statuses.reserved'),
            'rejected' => __('admin.operations.purchase_statuses.rejected'),
            'cancelled' => __('admin.operations.purchase_statuses.cancelled'),
            'expired' => __('admin.operations.purchase_statuses.expired'),
            'unknown' => __('admin.operations.purchase_statuses.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.purchase_statuses.unknown'),
        };
    }

    public static function purchaseStatusColor(?string $state): string
    {
        return match ($state) {
            'paid' => 'success',
            'payment_submitted', 'under_review' => 'warning',
            'reserved' => 'info',
            'rejected', 'cancelled', 'expired' => 'danger',
            default => 'gray',
        };
    }

    public static function paymentStatusLabel(?string $state): string
    {
        return match ($state) {
            'approved' => __('admin.operations.payment_statuses.approved'),
            'pending_review' => __('admin.operations.payment_statuses.pending_review'),
            'pending_review_overdue' => __('admin.operations.payment_statuses.pending_review_overdue'),
            'rejected' => __('admin.operations.payment_statuses.rejected'),
            'none' => __('admin.operations.payment_statuses.none'),
            'unknown' => __('admin.operations.payment_statuses.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.payment_statuses.unknown'),
        };
    }

    public static function paymentStatusColor(?string $state): string
    {
        return match ($state) {
            'approved' => 'success',
            'pending_review' => 'warning',
            'pending_review_overdue' => 'danger',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function whatsappMessageStatusLabel(?string $state): string
    {
        return match ($state) {
            'queued' => __('admin.operations.whatsapp_message_statuses.queued'),
            'generated' => __('admin.operations.whatsapp_message_statuses.generated'),
            'sent' => __('admin.operations.whatsapp_message_statuses.sent'),
            'failed' => __('admin.operations.whatsapp_message_statuses.failed'),
            'not_sent' => __('admin.operations.whatsapp_message_statuses.not_sent'),
            'unknown' => __('admin.operations.whatsapp_message_statuses.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.whatsapp_message_statuses.unknown'),
        };
    }

    public static function whatsappMessageStatusColor(?string $state): string
    {
        return match ($state) {
            'sent' => 'success',
            'queued', 'generated' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function whatsappProviderStatusLabel(?string $state): string
    {
        return match ($state) {
            'sent' => __('admin.operations.whatsapp_provider_statuses.sent'),
            'delivered' => __('admin.operations.whatsapp_provider_statuses.delivered'),
            'read' => __('admin.operations.whatsapp_provider_statuses.read'),
            'failed' => __('admin.operations.whatsapp_provider_statuses.failed'),
            'unknown' => __('admin.operations.whatsapp_provider_statuses.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.whatsapp_provider_statuses.unknown'),
        };
    }

    public static function whatsappProviderStatusColor(?string $state): string
    {
        return match ($state) {
            'sent' => 'info',
            'delivered' => 'success',
            'read' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function whatsappIntentLabel(?string $state): string
    {
        return match ($state) {
            'raffle_winner_notification' => __('admin.operations.whatsapp_intents.raffle_winner_notification'),
            'payment_approved_ticket' => __('admin.operations.whatsapp_intents.payment_approved_ticket'),
            'purchase_payment_reminder' => __('admin.operations.whatsapp_intents.purchase_payment_reminder'),
            'raffle_draw_reminder' => __('admin.operations.whatsapp_intents.raffle_draw_reminder'),
            'upcoming_raffle_announcement' => __('admin.operations.whatsapp_intents.upcoming_raffle_announcement'),
            'ticket_delivery' => __('admin.operations.whatsapp_intents.ticket_delivery'),
            'generic' => __('admin.operations.whatsapp_intents.generic'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.whatsapp_intents.generic'),
        };
    }

    public static function whatsappIntentColor(?string $state): string
    {
        return match ($state) {
            'raffle_winner_notification' => 'success',
            'payment_approved_ticket' => 'warning',
            'purchase_payment_reminder' => 'warning',
            'raffle_draw_reminder' => 'info',
            'upcoming_raffle_announcement' => 'success',
            'ticket_delivery' => 'info',
            default => 'gray',
        };
    }

    public static function whatsappPricingCategoryLabel(?string $state): string
    {
        return match ($state) {
            'service' => __('admin.operations.whatsapp_pricing_categories.service'),
            'utility' => __('admin.operations.whatsapp_pricing_categories.utility'),
            'marketing' => __('admin.operations.whatsapp_pricing_categories.marketing'),
            'authentication' => __('admin.operations.whatsapp_pricing_categories.authentication'),
            'unknown' => __('admin.operations.whatsapp_pricing_categories.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.whatsapp_pricing_categories.unknown'),
        };
    }

    public static function whatsappPricingCategoryColor(?string $state): string
    {
        return match ($state) {
            'service' => 'success',
            'utility' => 'info',
            'marketing' => 'warning',
            'authentication' => 'gray',
            default => 'gray',
        };
    }

    public static function ticketAttentionReasonLabel(string $state): string
    {
        return match ($state) {
            'failed' => __('admin.operations.ticket_attention_reasons.failed'),
            'without_delivery' => __('admin.operations.ticket_attention_reasons.without_delivery'),
            'delivered_not_read' => __('admin.operations.ticket_attention_reasons.delivered_not_read'),
            'awaiting_delivery' => __('admin.operations.ticket_attention_reasons.awaiting_delivery'),
            default => __('admin.operations.ticket_attention_reasons.unknown'),
        };
    }

    public static function ticketAttentionReasonColor(string $state): string
    {
        return match ($state) {
            'failed' => 'danger',
            'without_delivery' => 'warning',
            'delivered_not_read' => 'warning',
            'awaiting_delivery' => 'info',
            default => 'gray',
        };
    }

    public static function conversationStatusLabel(?string $state): string
    {
        return match ($state) {
            'main_menu' => __('admin.operations.conversation_statuses.main_menu'),
            'purchase_select_raffle' => __('admin.operations.conversation_statuses.purchase_select_raffle'),
            'purchase_enter_quantity' => __('admin.operations.conversation_statuses.purchase_enter_quantity'),
            'purchase_choose_mode' => __('admin.operations.conversation_statuses.purchase_choose_mode'),
            'purchase_select_numbers' => __('admin.operations.conversation_statuses.purchase_select_numbers'),
            'purchase_payment_instructions' => __('admin.operations.conversation_statuses.purchase_payment_instructions'),
            'purchase_rejected' => __('admin.operations.conversation_statuses.purchase_rejected'),
            'purchase_paid' => __('admin.operations.conversation_statuses.purchase_paid'),
            'purchase_under_review' => __('admin.operations.conversation_statuses.purchase_under_review'),
            'onboarding_privacy_consent' => __('admin.operations.conversation_statuses.onboarding_privacy_consent'),
            'onboarding_collect_name' => __('admin.operations.conversation_statuses.onboarding_collect_name'),
            'onboarding_collect_document' => __('admin.operations.conversation_statuses.onboarding_collect_document'),
            'unknown' => __('admin.operations.conversation_statuses.unknown'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.operations.conversation_statuses.unknown'),
        };
    }

    public static function conversationStatusColor(?string $state): string
    {
        return match ($state) {
            'purchase_paid' => 'success',
            'main_menu' => 'gray',
            'purchase_payment_instructions', 'purchase_rejected', 'purchase_under_review' => 'warning',
            'purchase_select_raffle', 'purchase_enter_quantity', 'purchase_choose_mode', 'purchase_select_numbers' => 'info',
            'onboarding_privacy_consent', 'onboarding_collect_name', 'onboarding_collect_document' => 'info',
            default => 'gray',
        };
    }
}
