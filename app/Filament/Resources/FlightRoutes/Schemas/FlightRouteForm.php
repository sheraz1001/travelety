<?php

namespace App\Filament\Resources\FlightRoutes\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlightRouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Route')->columns(4)->schema([
                TextInput::make('origin_iata')->label('From')->required()->maxLength(3),
                TextInput::make('destination_iata')->label('To')->required()->maxLength(3),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('/flights/')->columnSpan(2),
                Toggle::make('is_published')->label('Published')->inline(false),
                Toggle::make('is_indexable')->label('Indexable by Google')->inline(false),
                TextInput::make('priority')->numeric()->default(100),
                Placeholder::make('fares')->label('Fare data')->content(fn ($record) => $record?->fares_updated_at
                    ? 'Lowest '.\App\Services\Links::money($record->min_price).' · cheapest month '.($record->cheapest_month ?? '—').' · updated '.$record->fares_updated_at->diffForHumans()
                    : 'No fares fetched yet'),
            ]),
            Section::make('Page content')->schema([
                RichEditor::make('content')->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
                Repeater::make('faqs')->schema([TextInput::make('q')->required(), Textarea::make('a')->rows(2)->required()])
                    ->collapsible()->itemLabel(fn ($state) => $state['q'] ?? null)->defaultItems(0),
            ]),
            Section::make('SEO')->columns(2)->schema([
                TextInput::make('meta_title')->maxLength(70),
                Textarea::make('meta_description')->rows(2)->maxLength(170),
            ]),
        ]);
    }
}
