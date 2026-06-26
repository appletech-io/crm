<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Industry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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

                Section::make('Microsoft / Outlook Settings')
                    ->description('Configure Microsoft Graph to send emails via your Outlook mailbox.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ms_tenant_id')
                            ->label('Tenant ID')
                            ->helperText('Found in Azure Active Directory → Overview'),
                        TextInput::make('ms_client_id')
                            ->label('Client ID (Application ID)')
                            ->helperText('Found in your Azure App Registration → Overview'),
                        TextInput::make('ms_client_secret')
                            ->label('Client Secret')
                            ->helperText('Created under App Registration → Certificates & secrets')
                            ->password()
                            ->revealable(),
                        TextInput::make('ms_sender_email')
                            ->label('Sender Email')
                            ->helperText('The mailbox emails will be sent from (must exist in your tenant)')
                            ->email(),
                    ]),
            ]);
    }
}
