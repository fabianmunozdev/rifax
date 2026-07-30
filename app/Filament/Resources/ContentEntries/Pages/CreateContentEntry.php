<?php

namespace App\Filament\Resources\ContentEntries\Pages;

use App\Filament\Resources\ContentEntries\ContentEntryResource;
use App\Models\ContentEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateContentEntry extends CreateRecord
{
    protected static string $resource = ContentEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureUniqueScopedKey($data);
        $data = $this->normalizeFormData($data);

        $userId = Auth::id();

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        if (($data['status'] ?? null) === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ensureUniqueScopedKey(array $data): void
    {
        $alreadyExists = ContentEntry::query()
            ->where('key', $data['key'])
            ->where('locale', $data['locale'])
            ->where('channel', $data['channel'])
            ->exists();

        if (! $alreadyExists) {
            return;
        }

        throw ValidationException::withMessages([
            'key' => 'The key must be unique for the selected locale and channel.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFormData(array $data): array
    {
        if (! in_array($data['type'] ?? null, ['faq_fixed', 'faq_parametrized'], true)) {
            $data['is_ai_eligible'] = false;
        }

        return $data;
    }
}
