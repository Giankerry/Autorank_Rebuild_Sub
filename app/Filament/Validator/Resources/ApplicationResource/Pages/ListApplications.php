<?php

namespace App\Filament\Validator\Resources\ApplicationResource\Pages;

use App\Filament\Validator\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Applications for Validation';
    }
}
