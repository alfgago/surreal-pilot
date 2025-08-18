<?php

namespace App\Filament\Company\Resources\BillingHistoryResource\Pages;

use App\Filament\Company\Resources\BillingHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBillingHistory extends ViewRecord
{
    protected static string $resource = BillingHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
