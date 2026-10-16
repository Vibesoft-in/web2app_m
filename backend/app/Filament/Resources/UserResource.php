<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Tenant Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
            Forms\Components\TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->label('Email Address')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('tenant_id')
                ->label('Tenant Account')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('role')
                ->label('Role')
                ->options([
                    'owner'  => 'Owner',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ])
                ->default('owner')
                ->required(),
            Forms\Components\TextInput::make('password')
                ->password()
                ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context): bool => $context === 'create'),
            Forms\Components\DateTimePicker::make('email_verified_at')
                ->label('Email Verified At')
                ->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant Account')
                    ->formatStateUsing(fn ($record) => "#{$record->tenant_id} {$record->tenant?->name}")
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof UserRole ? $state->value : (string) $state) {
                        'owner'  => 'success',
                        'editor' => 'info',
                        default  => 'gray',
                    }),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_now')
                    ->label('Verify Email')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->email_verified_at === null)
                    ->action(function (User $record) {
                        $record->markEmailAsVerified();
                        Notification::make()
                            ->title('Email verified successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\Pages\ListUsers::route('/'),
        ];
    }
}
