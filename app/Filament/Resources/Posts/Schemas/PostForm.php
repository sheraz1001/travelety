<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Article')->schema([
                    TextInput::make('title')->required()->maxLength(120)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('/blog/'),
                    Textarea::make('excerpt')->rows(2)->helperText('Shown under the title and on cards. 1–2 sentences.'),
                    RichEditor::make('body')->required()->columnSpanFull()
                        ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote', 'table'], ['attachFiles'], ['undo', 'redo']])
                        ->fileAttachmentsDirectory('blog')->extraInputAttributes(['style' => 'min-height: 32rem']),
                ]),
                Section::make('FAQs')->description('Shown at the end of the article and added to search results as FAQ schema.')->collapsible()->schema([
                    Repeater::make('faqs')->hiddenLabel()->schema([
                        TextInput::make('q')->label('Question')->required(),
                        Textarea::make('a')->label('Answer')->rows(2)->required(),
                    ])->collapsible()->itemLabel(fn (array $state) => $state['q'] ?? null)->defaultItems(0),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Publishing')->schema([
                    Select::make('status')->options(['draft' => 'Draft', 'review' => 'Needs review', 'published' => 'Published'])->default('draft')->required(),
                    DateTimePicker::make('published_at')->label('Publish at')->helperText('Future date = scheduled.'),
                    Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                    Select::make('destination_id')->label('Destination')->relationship('destination', 'city')->searchable()->preload(),
                    TextInput::make('author_name')->default('Travelety Editorial')->required(),
                ]),
                Section::make('Cover image')->schema([
                    FileUpload::make('cover_image')->image()->directory('covers')->imageEditor()->maxSize(4096)->hiddenLabel(),
                    TextInput::make('cover_alt')->label('Alt text'),
                ]),
                Section::make('SEO')->schema([
                    TextInput::make('focus_keyword'),
                    TextInput::make('meta_title')->maxLength(70)->helperText('Up to 60 characters is ideal.'),
                    Textarea::make('meta_description')->rows(3)->maxLength(170)->helperText('140–155 characters.'),
                    TextInput::make('canonical_url')->url(),
                    Toggle::make('noindex')->label('Hide from Google (noindex)'),
                ]),
                Section::make('Quality check')->collapsed()->schema([
                    Placeholder::make('qc')->hiddenLabel()->content(function ($record) {
                        $r = $record?->quality_report;
                        if (! $r) {
                            return 'Not checked yet.';
                        }

                        return ($r['passed'] ? '✅ Passed' : '⚠️ Needs work').' — '.collect($r['checks'])->map(fn ($c, $k) => ($c['pass'] ? '✓ ' : '✗ ').str_replace('_', ' ', $k).(isset($c['value']) ? " ({$c['value']})" : ''))->implode(' · ');
                    }),
                ]),
            ]),
        ]);
    }
}
