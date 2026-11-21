<?php

namespace App\Filament\Resources\Destinations\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DestinationForm
{
    public static function configure(Schema $schema): Schema
    {
        $rich = fn ($n) => RichEditor::make($n)->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]);

        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextInput::make('city')->required(),
                TextInput::make('country')->required(),
                TextInput::make('iata')->label('Airport code')->maxLength(3),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                TextInput::make('tagline')->columnSpan(2),
                Toggle::make('is_published')->label('Published')->inline(false),
                Toggle::make('is_indexable')->label('Indexable by Google')->inline(false)->default(true),
                Toggle::make('is_featured')->label('Featured')->inline(false),
            ]),
            Tabs::make()->tabs([
                Tab::make('Travel guide')->schema([
                    $rich('intro'),
                    Textarea::make('best_time')->rows(2),
                    Textarea::make('visa_uae')->label('Visa for UAE residents')->rows(2),
                    $rich('guide_content'),
                ]),
                Tab::make('Hotels page')->schema([
                    Repeater::make('areas')->label('Areas to stay')->schema([
                        TextInput::make('name')->required(),
                        Select::make('budget')->options(['budget' => 'Budget', 'mid' => 'Mid-range', 'luxury' => 'Luxury']),
                        Textarea::make('description')->rows(2)->columnSpanFull(),
                    ])->columns(2)->collapsible()->itemLabel(fn ($state) => $state['name'] ?? null)->defaultItems(0),
                    $rich('hotels_content'),
                ]),
                Tab::make('Things to do & weather')->schema([
                    Repeater::make('things_to_do')->schema([TextInput::make('name')->required(), Textarea::make('description')->rows(2)])
                        ->collapsible()->itemLabel(fn ($state) => $state['name'] ?? null)->defaultItems(0),
                    Repeater::make('weather')->schema([TextInput::make('month'), TextInput::make('avg_high_c')->numeric()->label('Avg high °C'), TextInput::make('note')])
                        ->columns(3)->defaultItems(0)->collapsed(),
                ]),
                Tab::make('FAQs')->schema([
                    Repeater::make('faqs')->hiddenLabel()->schema([TextInput::make('q')->required(), Textarea::make('a')->rows(2)->required()])
                        ->collapsible()->itemLabel(fn ($state) => $state['q'] ?? null)->defaultItems(0),
                ]),
                Tab::make('SEO')->schema([
                    TextInput::make('meta_title')->maxLength(70),
                    Textarea::make('meta_description')->rows(2)->maxLength(170),
                ]),
            ]),
        ]);
    }
}
