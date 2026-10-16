<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Security & Audit';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('action')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('target')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('tenant_id')->label('Tenant ID')->placeholder('Global')->sortable(),
                Tables\Columns\TextColumn::make('actor_id')->label('Actor ID')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip')->label('IP Address')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AuditLogResource\Pages\ListAuditLogs::route('/'),
        ];
    }
}
