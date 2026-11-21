<?php

namespace App\Filament\Resources\Subscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('origin_iata'),
                TextInput::make('destination_iata'),
                TextInput::make('target_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('last_notified_price')
                    ->numeric()
                    ->prefix('$'),
                DateTimePicker::make('last_notified_at'),
                TextInput::make('token')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
