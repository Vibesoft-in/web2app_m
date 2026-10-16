<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Billing & Subscriptions';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('price_monthly')
                ->label('Monthly Price (in Paise)')
                ->helperText('100 Paise = ₹1 (Example: Enter 99900 for ₹999, 199900 for ₹1,999)')
                ->numeric()
                ->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('Monthly Price')
                    ->formatStateUsing(fn (int $state): string => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PlanResource\Pages\ListPlans::route('/'),
        ];
    }
}
