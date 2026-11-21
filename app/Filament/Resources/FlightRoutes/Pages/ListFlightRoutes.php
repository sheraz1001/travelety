<?php

namespace App\Filament\Resources\FlightRoutes\Pages;

use App\Filament\Resources\FlightRoutes\FlightRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlightRoutes extends ListRecords
{
    protected static string $resource = FlightRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
