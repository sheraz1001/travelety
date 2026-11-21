<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopClicks extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top clicked searches (30 days)';

    public function table(Table $table): Table
    {
        return $table
            ->query(Click::query()->select('type', 'target', DB::raw('count(*) as clicks'), DB::raw('min(id) as id'))
                ->where('created_at', '>=', now()->subDays(30))->groupBy('type', 'target')->orderByDesc('clicks'))
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('target')->label('Route / partner'),
                TextColumn::make('clicks')->numeric(),
            ])->paginated([10]);
    }
}
