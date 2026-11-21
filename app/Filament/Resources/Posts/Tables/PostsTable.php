<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Services\QualityChecker;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->limit(60)->weight('bold')
                    ->description(fn (Post $p) => $p->focus_keyword),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'published' => 'success', 'review' => 'warning', default => 'gray' }),
                TextColumn::make('category.name')->toggleable(),
                IconColumn::make('quality_report.passed')->label('QC')->boolean()->default(null),
                IconColumn::make('ai_generated')->label('AI')->boolean()->toggleable(),
                TextColumn::make('views')->numeric()->sortable(),
                TextColumn::make('published_at')->dateTime('j M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'review' => 'Needs review', 'published' => 'Published']),
                SelectFilter::make('category')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('view')->icon('heroicon-o-eye')->url(fn (Post $p) => $p->status === 'published' ? $p->url() : null, true)->visible(fn (Post $p) => $p->status === 'published'),
                Action::make('publish')->icon('heroicon-o-rocket-launch')->color('success')->requiresConfirmation()
                    ->visible(fn (Post $p) => $p->status !== 'published')
                    ->action(function (Post $p) {
                        $p->update(['status' => 'published', 'published_at' => $p->published_at ?? now()]);
                        Notification::make()->title('Published')->success()->send();
                    }),
                Action::make('check')->label('Run QC')->icon('heroicon-o-clipboard-document-check')
                    ->action(function (Post $p) {
                        $r = app(QualityChecker::class)->check($p, (int) \App\Models\Setting::get('blog_min_words'));
                        $p->update(['quality_report' => $r]);
                        Notification::make()->title($r['passed'] ? 'All checks passed' : 'Some checks failed')->{$r['passed'] ? 'success' : 'warning'}()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->icon('heroicon-o-rocket-launch')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'published', 'published_at' => now()])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
