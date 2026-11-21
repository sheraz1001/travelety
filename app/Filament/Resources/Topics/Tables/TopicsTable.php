<?php

namespace App\Filament\Resources\Topics\Tables;

use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class TopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('keyword')->searchable()->weight('bold')->description(fn (Topic $t) => $t->error),
                TextColumn::make('category.name'),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'done' => 'success', 'failed' => 'danger', 'writing' => 'warning', default => 'gray' }),
                TextColumn::make('priority')->sortable(),
                TextColumn::make('post.title')->label('Article')->limit(40),
            ])
            ->filters([SelectFilter::make('status')->options(['queued' => 'Queued', 'done' => 'Done', 'failed' => 'Failed'])])
            ->recordActions([
                Action::make('write')->label('Write now')->icon('heroicon-o-sparkles')->visible(fn (Topic $t) => $t->status !== 'done')
                    ->action(function (Topic $t) {
                        Artisan::call('blog:daily', ['--topic' => $t->id, '--force' => true]);
                        $t->refresh();
                        Notification::make()->title($t->status === 'done' ? 'Draft written: '.$t->post?->title : 'Failed: '.$t->error)->{$t->status === 'done' ? 'success' : 'danger'}()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
