<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Enums\PanelRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $beforeSnapshot = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        $this->beforeSnapshot = $record->attributesToArray();

        $becomesAdmin = ($data['role'] ?? $record->role) === PanelRole::Admin->value;
        $remainsActive = (bool) ($data['is_active'] ?? $record->is_active);

        if (
            $record->role === PanelRole::Admin->value
            && $record->is_active
            && (! $becomesAdmin || ! $remainsActive)
            && User::query()
                ->where('role', PanelRole::Admin->value)
                ->where('is_active', true)
                ->whereKeyNot($record->getKey())
                ->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'role' => 'At least one active admin account must remain available.',
            ]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $audit = app(RecordAdminAuditAction::class);

        $audit->execute(
            event: 'user.updated',
            action: 'update',
            auditable: $this->record,
            before: $this->beforeSnapshot,
            after: $audit->snapshot($this->record),
            context: [
                'role' => $this->record->role,
                'is_active' => $this->record->is_active,
            ],
        );
    }
}
