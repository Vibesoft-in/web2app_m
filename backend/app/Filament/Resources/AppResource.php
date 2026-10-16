<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\App;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppResource extends Resource
{
    protected static ?string $model = App::class;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationGroup = 'Apps & Builds';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant Account')
                    ->formatStateUsing(function ($record) {
                        $userEmail = $record->tenant?->users?->first()?->email;
                        return "#{$record->tenant_id} {$record->tenant?->name}" . ($userEmail ? " ({$userEmail})" : "");
                    })
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('platform')->badge(),
                Tables\Columns\TextColumn::make('setup_step')->label('Step')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('package_name')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AppResource\Pages\ListApps::route('/'),
        ];
    }
}
