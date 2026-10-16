<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\UsageEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UsageEventResource extends Resource
{
    protected static ?string $model = UsageEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Billing & Subscriptions';
    protected static ?string $navigationLabel = 'Usage & Cost';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->label('Tenant')->sortable(),
                Tables\Columns\TextColumn::make('app.name')->label('App')->placeholder('Tenant Level'),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('qty')->sortable(),
                Tables\Columns\TextColumn::make('cost')
                    ->formatStateUsing(fn ($state) => '₹'.number_format((float) $state, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => UsageEventResource\Pages\ListUsageEvents::route('/'),
        ];
    }
}
