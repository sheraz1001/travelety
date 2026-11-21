<?php

namespace App\Filament\Resources\Destinations\Tables;

use App\Models\Destination;
use App\Services\ContentGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DestinationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('is_featured', 'desc')
            ->columns([
                TextColumn::make('city')->searchable()->sortable()->weight('bold')->description(fn (Destination $d) => $d->country),
                TextColumn::make('iata')->label('Code'),
                IconColumn::make('content_generated_at')->label('Content')->boolean()->getStateUsing(fn (Destination $d) => (bool) $d->guide_content),
                ToggleColumn::make('is_published')->label('Live'),
                ToggleColumn::make('is_featured')->label('Featured'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
                TernaryFilter::make('has_content')->label('Has content')->queries(
                    true: fn ($q) => $q->whereNotNull('guide_content'), false: fn ($q) => $q->whereNull('guide_content')),
            ])
            ->recordActions([
                Action::make('open')->icon('heroicon-o-eye')->url(fn (Destination $d) => $d->guideUrl(), true)->visible(fn (Destination $d) => $d->is_published),
                Action::make('generate')->label('Write with AI')->icon('heroicon-o-sparkles')->requiresConfirmation()
                    ->modalDescription('Writes the guide, hotels page, areas, weather and FAQs. Existing text is replaced.')
                    ->action(function (Destination $d) {
                        try {
                            app(ContentGenerator::class)->forDestination($d);
                            Notification::make()->title('Content written — review it, then publish')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Failed: '.$e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publish')->icon('heroicon-o-check')->action(fn (Collection $r) => $r->each->update(['is_published' => true])),
                BulkAction::make('unpublish')->icon('heroicon-o-x-mark')->action(fn (Collection $r) => $r->each->update(['is_published' => false])),
            ])]);
    }
}
