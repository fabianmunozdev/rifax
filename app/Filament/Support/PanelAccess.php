<?php

namespace App\Filament\Support;

use App\Enums\PanelPermission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PanelAccess
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function allows(PanelPermission|string $permission): bool
    {
        $user = static::user();

        if ($user === null) {
            return false;
        }

        return $user->hasPanelPermission($permission);
    }

    /**
     * @param  list<PanelPermission|string>  $permissions
     */
    public static function allowsAny(array $permissions): bool
    {
        $user = static::user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyPanelPermission($permissions);
    }
}
