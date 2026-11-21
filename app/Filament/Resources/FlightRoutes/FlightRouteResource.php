<?php

namespace App\Filament\Resources\FlightRoutes;

use App\Filament\Resources\FlightRoutes\Pages\CreateFlightRoute;
use App\Filament\Resources\FlightRoutes\Pages\EditFlightRoute;
use App\Filament\Resources\FlightRoutes\Pages\ListFlightRoutes;
use App\Filament\Resources\FlightRoutes\Schemas\FlightRouteForm;
use App\Filament\Resources\FlightRoutes\Tables\FlightRoutesTable;
use App\Models\FlightRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FlightRouteResource extends Resource
{
    protected static ?string $model = FlightRoute::class;

    protected static ?string $navigationLabel = 'Flight routes';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'slug';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function form(Schema $schema): Schema
    {
        return FlightRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlightRoutesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlightRoutes::route('/'),
            'create' => CreateFlightRoute::route('/create'),
            'edit' => EditFlightRoute::route('/{record}/edit'),
        ];
    }
}
