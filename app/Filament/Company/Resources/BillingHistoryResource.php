<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\BillingHistoryResource\Pages;
use App\Models\BillingHistory;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Forms\Components as Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingHistoryResource extends Resource
{
    protected static ?string $model = BillingHistory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'Billing History';
    
    protected static ?string $modelLabel = 'Billing Record';
    
    protected static ?string $pluralModelLabel = 'Billing History';
    
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2))
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->disabled(),
                Forms\Components\TextInput::make('credits_added')
                    ->label('Credits Added')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('processed_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'credit_purchase',
                        'primary' => 'subscription_payment',
                        'warning' => 'refund',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit_purchase' => 'Credit Purchase',
                        'subscription_payment' => 'Subscription',
                        'refund' => 'Refund',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                Tables\Columns\TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('amount_cents', $direction);
                    }),
                Tables\Columns\TextColumn::make('credits_added')
                    ->label('Credits')
                    ->formatStateUsing(fn ($state) => $state ? '+' . number_format($state) : '-')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'succeeded',
                        'danger' => 'failed',
                        'warning' => 'pending',
                        'secondary' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'credit_purchase' => 'Credit Purchase',
                        'subscription_payment' => 'Subscription Payment',
                        'refund' => 'Refund',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('processed_at', 'desc')
            ->emptyStateHeading('No billing history')
            ->emptyStateDescription('Your billing transactions will appear here once you make purchases or payments.');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingHistories::route('/'),
            'view' => Pages\ViewBillingHistory::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
