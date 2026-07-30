<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $audit = app(RecordAdminAuditAction::class);

        $audit->execute(
            event: 'user.created',
            action: 'create',
            auditable: $this->record,
            before: null,
            after: $audit->snapshot($this->record),
            context: [
                'role' => $this->record->role,
                'is_active' => $this->record->is_active,
            ],
        );
    }
}
