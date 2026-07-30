<?php

namespace App\Filament\Resources\Raffles\Pages;

use App\Actions\Raffles\ProvisionRaffleNumbersAction;
use App\Filament\Resources\Raffles\RaffleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRaffle extends CreateRecord
{
    protected static string $resource = RaffleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record === null) {
            return;
        }

        app(ProvisionRaffleNumbersAction::class)->execute($this->record);
    }
}
