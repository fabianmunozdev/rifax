<?php

namespace App\Filament\Resources\CompanySettings\Pages;

use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Models\CompanySetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanySettings extends ListRecords
{
    protected static string $resource = CompanySettingResource::class;

    public function mount(): void
    {
        parent::mount();

        $setting = CompanySetting::query()->first();

        if ($setting !== null) {
            $this->redirect(CompanySettingResource::getUrl('edit', ['record' => $setting]));

            return;
        }

        if (CompanySettingResource::canCreate()) {
            $this->redirect(CompanySettingResource::getUrl('create'));
        }
    }

    protected function getHeaderActions(): array
    {
        return CompanySettingResource::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
