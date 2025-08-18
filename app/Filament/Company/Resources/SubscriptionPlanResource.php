<?php

namespace App\Filament\Company\Resources;

use App\Models\SubscriptionPlan;
use Filament\Forms\Components as Forms;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Resources\Resource;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $navigationLabel = 'Plans';

    // Disable tenancy for this resource since SubscriptionPlan is global
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    // Override query to not filter by tenant
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\TextInput::make('name')->required(),
            Forms\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\TextInput::make('monthly_credits')->numeric()->required(),
            Forms\TextInput::make('price_cents')->numeric()->required(),
            Forms\TextInput::make('stripe_price_id')->label('Stripe Price ID'),
            Forms\Toggle::make('allow_byo_keys')->label('Allow BYO API Keys'),
            Forms\TextInput::make('addon_price_cents')->numeric()->label('Add-on price (cents)'),
            Forms\TextInput::make('addon_credits_per_unit')->numeric()->label('Add-on credits'),
            Forms\KeyValue::make('features')->keyLabel('Feature')->valueLabel('Details')->reorderable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\TextColumn::make('monthly_credits')->label('Monthly Credits'),
            Tables\Columns\TextColumn::make('price_cents')->money('usd', divideBy: 100)->label('Price'),
            Tables\Columns\IconColumn::make('allow_byo_keys')->boolean()->label('BYO Keys'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }
}

