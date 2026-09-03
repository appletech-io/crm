<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Integration;
use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Models\ClientContact;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserForm
{
    use HasPayrollProviderErrorAlert;

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
                        TextInput::make('mobile')
                            ->tel()
                            ->maxLength(255)
                            ->helperText('Shown in this user\'s email signature footer, if set.'),
                        TextInput::make('job_title')
                            ->label('Job Title')
                            ->maxLength(255)
                            ->placeholder('Consultant')
                            ->helperText('Shown under their name in the email signature footer. Defaults to "Consultant" if left blank.'),
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

                Section::make('Payroll Provider')
                    ->hidden()
                    ->schema([
                        TextInput::make('payroll_provider_id')
                            ->label('Payroll Provider ID')
                            ->helperText('This consultant\'s existing Consultant ID in the agency\'s payroll provider, if one already exists there.')
                            ->dehydrated(false)
                            ->afterStateHydrated(function (TextInput $component, ?User $record): void {
                                $provider = Auth::user()->company->payroll_provider;

                                if ($record && $provider instanceof Integration) {
                                    $component->state($record->providerExternalId($provider));
                                }
                            }),
                    ]),

                Section::make('Payroll')
                    ->visible(fn (?User $record): bool => $record !== null)
                    ->schema([
                        // Shown regardless of whether the company currently has
                        // Evertime enabled as its active payroll_provider — a
                        // consultant can already have a synced/manually-entered
                        // ID from before that toggle changed, or before it's set
                        // at all, and that shouldn't hide an ID that already exists.
                        TextEntry::make('payroll_provider_id_display')
                            ->label('Payroll Provider ID')
                            ->getStateUsing(fn (?User $record): ?string => $record?->providerExternalId(Integration::Evertime))
                            ->placeholder('Not yet synced'),
                    ]),

                Section::make('Payroll Submission Failed')
                    ->columnSpanFull()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(fn (?User $record): bool => $record && $record->hasRole('consultant') && static::currentProviderErrors($record)->isNotEmpty())
                    ->schema([
                        Textarea::make('payroll_provider_errors')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Textarea $component, ?User $record): void {
                                if ($record) {
                                    $component->state(static::currentProviderErrors($record)->implode("\n"));
                                }
                            }),
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
