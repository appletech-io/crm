<?php

namespace App\Filament\Widgets;

use App\Enums\Integration;
use App\Models\PaymentProvider;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Embedded on the Evertime integration settings page — this app's only
 * payroll provider for now, so the external ID field below is hardcoded to
 * it rather than taking a mount parameter for a hypothetical second one.
 */
class PaymentProvidersOverview extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var array<int, string> */
    private const array FIELDS = [
        'name', 'address_1', 'address_2', 'county', 'postcode',
        'contact_first_name', 'contact_last_name', 'contact_phone', 'email', 'phone',
        'company_reg_number', 'vat_reg_number', 'utr',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_sort_code',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => PaymentProvider::query()->where('company_id', Auth::user()->company_id))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('postcode')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('county')
                    ->placeholder('—'),
                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->state(fn (PaymentProvider $record): ?string => trim("{$record->contact_first_name} {$record->contact_last_name}") ?: null)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New Payment Provider')
                    ->modalHeading('Add payment provider')
                    ->schema(static::formSchema())
                    ->action(function (array $data): void {
                        $paymentProvider = PaymentProvider::create(
                            collect($data)->only(self::FIELDS)->all()
                        );

                        $paymentProvider->setProviderExternalId(Integration::Evertime, $data['payroll_provider_id'] ?? null);
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->fillForm(fn (PaymentProvider $record): array => [
                        ...collect($record)->only(self::FIELDS)->all(),
                        'payroll_provider_id' => $record->providerExternalId(Integration::Evertime),
                    ])
                    ->schema(static::formSchema())
                    ->action(function (PaymentProvider $record, array $data): void {
                        $record->update(collect($data)->only(self::FIELDS)->all());

                        $record->setProviderExternalId(Integration::Evertime, $data['payroll_provider_id'] ?? null);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No payment providers yet')
            ->emptyStateDescription('Add the umbrella/Ltd companies your candidates are paid through here.');
    }

    /** @return array<int, Section> */
    private static function formSchema(): array
    {
        return [
            Section::make('Company')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Company Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('address_1')
                        ->label('Address Line 1'),
                    TextInput::make('address_2')
                        ->label('Address Line 2'),
                    TextInput::make('county'),
                    TextInput::make('postcode'),
                    TextInput::make('company_reg_number')
                        ->label('Company Registration Number')
                        ->maxLength(8)
                        ->helperText('Required by Evertime for a new UK company.'),
                    TextInput::make('vat_reg_number')
                        ->label('VAT Registration Number')
                        ->maxLength(20),
                    TextInput::make('utr')
                        ->label('UTR')
                        ->maxLength(10),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_first_name')
                        ->label('Contact First Name')
                        ->helperText('The named contact Evertime requires for this company.')
                        ->maxLength(255),
                    TextInput::make('contact_last_name')
                        ->label('Contact Last Name')
                        ->maxLength(255),
                    TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->maxLength(30),
                    TextInput::make('email')
                        ->label('Company Email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label('Company Phone')
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make('Bank Details')
                ->columns(2)
                ->schema([
                    TextInput::make('bank_name')
                        ->maxLength(50),
                    TextInput::make('bank_account_name')
                        ->label('Account Name')
                        ->maxLength(50),
                    TextInput::make('bank_account_number')
                        ->label('Account Number')
                        ->maxLength(8),
                    TextInput::make('bank_sort_code')
                        ->label('Sort Code')
                        ->placeholder('00-00-00')
                        ->maxLength(8),
                ]),

            Section::make('Payroll Provider')
                ->schema([
                    TextInput::make('payroll_provider_id')
                        ->label('Payroll Provider ID')
                        ->helperText('This payment provider\'s existing ID in Evertime, if one already exists there.'),
                ]),
        ];
    }
}
