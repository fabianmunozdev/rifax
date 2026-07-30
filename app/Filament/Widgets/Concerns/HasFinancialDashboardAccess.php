<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\PanelRole;
use App\Filament\Support\PanelAccess;

trait HasFinancialDashboardAccess
{
    public static function canView(): bool
    {
        $user = PanelAccess::user();
        $role = $user?->roleEnum();

        return ($user?->is_active === true) && in_array($role, [
            PanelRole::Admin,
            PanelRole::Finance,
        ], true);
    }
}
