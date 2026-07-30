<?php

namespace App\Enums;

enum PanelRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Finance = 'finance';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('admin.roles.admin'),
            self::Operator => __('admin.roles.operator'),
            self::Finance => __('admin.roles.finance'),
            self::Support => __('admin.roles.support'),
        };
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => PanelPermission::values(),
            self::Operator => [
                PanelPermission::DashboardView->value,
                PanelPermission::RafflesView->value,
                PanelPermission::CustomersView->value,
                PanelPermission::ConversationsView->value,
                PanelPermission::ConversationsManage->value,
                PanelPermission::PurchasesView->value,
                PanelPermission::PaymentsView->value,
                PanelPermission::TicketsView->value,
                PanelPermission::TicketsManage->value,
                PanelPermission::WhatsappMessagesView->value,
                PanelPermission::WhatsappMessagesManage->value,
            ],
            self::Finance => [
                PanelPermission::DashboardView->value,
                PanelPermission::RafflesView->value,
                PanelPermission::CustomersView->value,
                PanelPermission::PurchasesView->value,
                PanelPermission::PaymentsView->value,
                PanelPermission::PaymentsReview->value,
                PanelPermission::TicketsView->value,
            ],
            self::Support => [
                PanelPermission::DashboardView->value,
                PanelPermission::ContentEntriesManage->value,
                PanelPermission::RafflesView->value,
                PanelPermission::CustomersView->value,
                PanelPermission::ConversationsView->value,
                PanelPermission::ConversationsManage->value,
                PanelPermission::PurchasesView->value,
                PanelPermission::TicketsView->value,
                PanelPermission::WhatsappMessagesView->value,
                PanelPermission::WhatsappMessagesManage->value,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
