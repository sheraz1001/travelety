<?php

namespace App\Filament\Resources\Topics\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('keyword')->required()->helperText('The search phrase the article should rank for (e.g. from Semrush).')->columnSpanFull(),
                TextInput::make('title_hint')->label('Working title (optional)')->columnSpanFull(),
                Select::make('category_id')->relationship('category', 'name')->preload(),
                TextInput::make('priority')->numeric()->default(100)->helperText('Lower = written sooner.'),
                TextInput::make('origin_iata')->label('Route from (optional)')->maxLength(3)->helperText('Adds live fare facts, e.g. DXB'),
                TextInput::make('destination_iata')->label('Route to (optional)')->maxLength(3),
                Textarea::make('notes')->label('Notes for the writer')->rows(3)->columnSpanFull(),
                Select::make('status')->options(['queued' => 'Queued', 'writing' => 'Writing', 'done' => 'Done', 'failed' => 'Failed', 'skipped' => 'Skipped'])->default('queued'),
            ]),
        ]);
    }
}
