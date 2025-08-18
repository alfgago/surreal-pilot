<?php

namespace App\Filament\Company\Resources\BillingHistoryResource\Pages;

use App\Filament\Company\Resources\BillingHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillingHistory extends EditRecord
{
    protected static string $resource = BillingHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
