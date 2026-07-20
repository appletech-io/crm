<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\ClientContact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company')
                    ->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                            ->helperText('Leave blank to keep the current password.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Roles')
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->live()
                            ->preload(),
                    ]),

                Section::make('Client Contact')
                    ->description('Link this login to a specific client contact so they can access the client portal.')
                    ->visible(fn (Get $get): bool => static::hasClientRole($get('roles')))
                    ->schema([
                        Select::make('client_contact_id')
                            ->label('Client Contact')
                            ->options(fn (Get $get): array => ClientContact::withoutGlobalScope('company')
                                ->where('company_id', $get('company_id'))
                                ->get()
                                ->mapWithKeys(fn (ClientContact $contact): array => [
                                    $contact->id => trim("{$contact->first_name} {$contact->last_name}").($contact->client ? " ({$contact->client->name})" : ''),
                                ])
                                ->toArray()
                            )
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Sectors')
                    ->schema([
                        Select::make('industries')
                            ->label('Sectors')
                            ->multiple()
                            ->relationship(
                                name: 'industries',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, Get $get) => $query
                                    ->whereIn('industries.id', function ($sub) use ($get) {
                                        $sub->select('industry_id')
                                            ->from('company_industry')
                                            ->where('company_id', $get('company_id'));
                                    }),
                            )
                            ->preload(),
                    ]),
            ]);
    }

    protected static function hasClientRole(mixed $roleIds): bool
    {
        if (blank($roleIds)) {
            return false;
        }

        return Role::whereIn('id', (array) $roleIds)->where('name', 'client')->exists();
    }
}
