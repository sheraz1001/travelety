<?php

namespace App\Filament\Resources\Airports\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AirportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iata')
                    ->required(),
                TextInput::make('city')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('country_code')
                    ->required(),
                TextInput::make('country'),
                TextInput::make('city_slug')
                    ->required(),
                Toggle::make('is_origin')
                    ->required(),
                Toggle::make('is_destination')
                    ->required(),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(100),
            ]);
    }
}
