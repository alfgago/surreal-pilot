<?php

namespace App\Filament\Company\Resources\BillingHistoryResource\Pages;

use App\Filament\Company\Resources\BillingHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillingHistories extends ListRecords
{
    protected static string $resource = BillingHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
