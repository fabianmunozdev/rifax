<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecordAdminAuditAction
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    public function execute(
        string $event,
        string $action,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        array $context = [],
        ?User $user = null,
    ): AdminAuditLog {
        $user ??= $this->resolveUser();

        return AdminAuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before_json' => $before,
            'after_json' => $after,
            'context_json' => $context,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    protected function resolveUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(?Model $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return $model->attributesToArray();
    }
}
