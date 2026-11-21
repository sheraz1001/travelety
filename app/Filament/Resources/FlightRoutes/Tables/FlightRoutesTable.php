<?php

namespace App\Filament\Resources\FlightRoutes\Tables;

use App\Models\FlightRoute;
use App\Services\ContentGenerator;
use App\Services\FareService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class FlightRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('slug')->label('Route')->searchable()->weight('bold')
                    ->description(fn (FlightRoute $r) => "{$r->origin_iata} → {$r->destination_iata}"),
                TextColumn::make('min_price')->label('Lowest fare')->formatStateUsing(fn ($state) => \App\Services\Links::money($state))->sortable(),
                TextColumn::make('cheapest_month')->label('Best month'),
                IconColumn::make('content')->label('Text')->boolean()->getStateUsing(fn (FlightRoute $r) => (bool) $r->content_generated_at),
                ToggleColumn::make('is_published')->label('Live'),
                ToggleColumn::make('is_indexable')->label('Index'),
                TextColumn::make('fares_updated_at')->label('Fares updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('origin_iata')->label('From')->options(['DXB' => 'Dubai', 'AUH' => 'Abu Dhabi', 'SHJ' => 'Sharjah']),
                TernaryFilter::make('is_published')->label('Published'),
                TernaryFilter::make('has_fares')->label('Has fares')->queries(true: fn ($q) => $q->whereNotNull('min_price'), false: fn ($q) => $q->whereNull('min_price')),
            ])
            ->recordActions([
                Action::make('open')->icon('heroicon-o-eye')->url(fn (FlightRoute $r) => $r->url(), true)->visible(fn (FlightRoute $r) => $r->is_published),
                Action::make('fares')->label('Update fares')->icon('heroicon-o-arrow-path')->action(function (FlightRoute $r) {
                    $ok = app(FareService::class)->refresh($r);
                    Notification::make()->title($ok ? 'Fares updated' : 'No fares found for this route yet')->{$ok ? 'success' : 'warning'}()->send();
                }),
                Action::make('write')->label('Write with AI')->icon('heroicon-o-sparkles')->action(function (FlightRoute $r) {
                    try {
                        app(ContentGenerator::class)->forRoute($r);
                        Notification::make()->title('Content written')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Failed: '.$e->getMessage())->danger()->send();
                    }
                }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publish')->icon('heroicon-o-check')->action(fn (Collection $r) => $r->each->update(['is_published' => true, 'is_indexable' => true])),
                BulkAction::make('unpublish')->icon('heroicon-o-x-mark')->action(fn (Collection $r) => $r->each->update(['is_published' => false])),
            ])]);
    }
}
