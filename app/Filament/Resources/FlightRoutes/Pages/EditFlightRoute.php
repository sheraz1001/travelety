<?php

namespace App\Filament\Resources\FlightRoutes\Pages;

use App\Filament\Resources\FlightRoutes\FlightRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFlightRoute extends EditRecord
{
    protected static string $resource = FlightRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
