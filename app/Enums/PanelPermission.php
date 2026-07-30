<?php

namespace App\Enums;

enum PanelPermission: string
{
    case DashboardView = 'dashboard.view';
    case UsersManage = 'users.manage';
    case AuditLogsView = 'audit_logs.view';
    case CompanySettingsManage = 'company_settings.manage';
    case PaymentMethodsManage = 'payment_methods.manage';
    case ContentEntriesManage = 'content_entries.manage';
    case RafflesView = 'raffles.view';
    case RafflesManage = 'raffles.manage';
    case CustomersView = 'customers.view';
    case ConversationsView = 'conversations.view';
    case ConversationsManage = 'conversations.manage';
    case PurchasesView = 'purchases.view';
    case PaymentsView = 'payments.view';
    case PaymentsReview = 'payments.review';
    case TicketsView = 'tickets.view';
    case TicketsManage = 'tickets.manage';
    case WhatsappMessagesView = 'whatsapp_messages.view';
    case WhatsappMessagesManage = 'whatsapp_messages.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
