<?php

namespace App\Filament\Resources\ChartAccountResource\Pages;

use App\Filament\Resources\ChartAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChartAccounts extends ManageRecords
{
    protected static string $resource = ChartAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة حساب')];
    }
}
