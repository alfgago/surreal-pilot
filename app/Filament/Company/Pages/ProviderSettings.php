<?php

namespace App\Filament\Company\Pages;

use Filament\Forms\Components as Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ProviderSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.company.pages.provider-settings';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Provider Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $company = Auth::user()?->currentCompany;
        $plan = $company?->subscriptionPlan;
        abort_unless($plan?->allow_byo_keys, 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make('Bring Your Own Keys')->schema([
                Forms\TextInput::make('openai_api_key')->password()->revealable()->label('OpenAI API Key'),
                Forms\TextInput::make('anthropic_api_key')->password()->revealable()->label('Anthropic API Key'),
                Forms\TextInput::make('gemini_api_key')->password()->revealable()->label('Gemini API Key'),
            ]),
        ]);
    }

    public function save(): void
    {
        $company = Auth::user()?->currentCompany;
        $plan = $company?->subscriptionPlan;
        abort_unless($plan?->allow_byo_keys, 403);

        $data = $this->form->getState();
        if (!empty($data['openai_api_key'])) {
            $company->openai_api_key_enc = Crypt::encryptString($data['openai_api_key']);
        }
        if (!empty($data['anthropic_api_key'])) {
            $company->anthropic_api_key_enc = Crypt::encryptString($data['anthropic_api_key']);
        }
        if (!empty($data['gemini_api_key'])) {
            $company->gemini_api_key_enc = Crypt::encryptString($data['gemini_api_key']);
        }
        $company->save();
        session()->flash('status', 'Provider settings saved');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $company = Auth::user()?->currentCompany;
        return (bool) $company?->subscriptionPlan?->allow_byo_keys;
    }
}

