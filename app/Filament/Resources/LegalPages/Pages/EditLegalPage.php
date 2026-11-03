<?php

namespace App\Filament\Resources\LegalPages\Pages;

use App\Filament\Resources\LegalPages\LegalPageResource;
use App\Models\LegalPage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditLegalPage extends EditRecord
{
    protected static string $resource = LegalPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        if (($data['is_published'] ?? false) && $this->record->published_at === null) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_public')
                ->label('Ver página')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (LegalPage $record): string => $record->publicUrl(), shouldOpenInNewTab: true),
        ];
    }
}
