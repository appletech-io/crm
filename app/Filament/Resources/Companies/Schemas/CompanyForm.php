<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\EmailProvider;
use App\Models\Company;
use App\Models\Industry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->helperText('Used in candidate/client emails, e.g. as a contact number for booking queries')
                            ->columnSpanFull(),
                        Select::make('industries')
                            ->relationship('industries', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Sectors')
                            ->columnSpanFull()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Industry::create([
                                    'name' => $data['name'],
                                    'slug' => Str::slug($data['name']),
                                ])->getKey();
                            })
                            ->editOptionForm([
                                TextInput::make('name')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Email Settings')
                    ->schema([
                        Select::make('email_provider')
                            ->label('Email Provider')
                            ->options(EmailProvider::options())
                            ->default(EmailProvider::Microsoft->value)
                            ->required()
                            ->live(),
                    ]),

                Section::make('Microsoft / Outlook')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('email_provider') === EmailProvider::Microsoft->value)
                    ->schema([
                        TextInput::make('ms_tenant_id')
                            ->label('Tenant ID')
                            ->helperText('Found in Azure Active Directory → Overview')
                            ->required(),
                        TextInput::make('ms_client_id')
                            ->label('Client ID (Application ID)')
                            ->helperText('Found in your Azure App Registration → Overview')
                            ->required(),
                        TextInput::make('ms_client_secret')
                            ->label('Client Secret')
                            ->helperText('Created under App Registration → Certificates & secrets')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (?Company $record): bool => blank($record?->ms_client_secret)),
                        TextInput::make('ms_sender_email')
                            ->label('Sender Email')
                            ->helperText('The mailbox emails will be sent from (must exist in your tenant)')
                            ->email()
                            ->required(),
                    ]),
            ]);
    }
}
