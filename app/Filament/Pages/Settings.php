<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(Setting::DEFAULTS)->map(fn ($v, $k) => Setting::get($k, $v))->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Brand')->columns(2)->schema([
                TextInput::make('site_name')->required(),
                TextInput::make('tagline'),
                TextInput::make('contact_email')->email(),
                TextInput::make('default_origin')->label('Default "From" airport')->maxLength(3),
            ]),
            Section::make('Search')->description('Your branded search domain and display options.')->columns(3)->schema([
                TextInput::make('search_domain')->url()->required()->columnSpan(3),
                Select::make('currency')->options(['aed' => 'AED', 'usd' => 'USD', 'sar' => 'SAR', 'qar' => 'QAR', 'kwd' => 'KWD', 'omr' => 'OMR', 'bhd' => 'BHD', 'pkr' => 'PKR', 'inr' => 'INR', 'eur' => 'EUR', 'gbp' => 'GBP']),
                Select::make('locale')->options(['en' => 'English', 'ar' => 'Arabic']),
            ]),
            Section::make('Partner links')->description('Paste the tracking links you create for each partner. Visitors only ever see links on your own domain (/go/...).')->columns(2)->schema([
                TextInput::make('link_hotels')->label('Hotels (optional)')->url(),
                TextInput::make('link_transfers')->label('Airport transfers')->url(),
                TextInput::make('link_tours')->label('Tours & tickets')->url(),
                TextInput::make('link_esim')->label('eSIM')->url(),
                TextInput::make('link_cars')->label('Car rental')->url(),
                TextInput::make('link_insurance')->label('Travel insurance')->url(),
                TextInput::make('link_compensation')->label('Flight compensation')->url(),
            ]),
            Section::make('Integrations (private)')->description('Kept on the server only, never shown on the website.')->columns(2)->schema([
                TextInput::make('fares_api_token')->label('Fare data API token')->password()->revealable()
                    ->helperText('Travelpayouts → Profile → API token'),
                TextInput::make('affiliate_marker')->label('Partner ID (marker)')->helperText('Your account ID, e.g. 402214'),
                Select::make('ai_provider')->label('AI provider')->options(['anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI']),
                TextInput::make('ai_model')->label('AI model'),
                TextInput::make('ai_api_key')->label('AI API key')->password()->revealable()->columnSpanFull(),
                TextInput::make('gettransfer_token')->label('GetTransfer API token')->password()->revealable()
                    ->helperText('Request from support@travelpayouts.com. Enables live transfer prices.'),
                TextInput::make('gettransfer_api_base')->label('GetTransfer API host'),
                TextInput::make('tp_project_id')->label('Project ID (for eSIM links)'),
                TextInput::make('link_gettransfer')->label('GetTransfer booking link')->url(),
            ]),
            Section::make('Content automation')->columns(4)->schema([
                Toggle::make('blog_auto_publish')->label('Auto-publish posts that pass QC')->inline(false)->columnSpan(2),
                TextInput::make('blog_posts_per_day')->numeric()->minValue(0)->maxValue(5),
                TextInput::make('blog_min_words')->numeric(),
                TextInput::make('routes_content_per_run')->label('Route pages written per day')->numeric(),
            ]),
            Section::make('Tracking & social')->columns(2)->schema([
                TextInput::make('google_analytics_id')->placeholder('G-XXXXXXX'),
                TextInput::make('google_site_verification'),
                TextInput::make('social_facebook')->url(),
                TextInput::make('social_instagram')->url(),
                TextInput::make('social_x')->url(),
            ]),
        ]);
    }

    public function save(): void
    {
        Setting::put($this->form->getState());
        cache()->forget('airports.js');
        Notification::make()->title('Settings saved')->success()->send();
    }
}
