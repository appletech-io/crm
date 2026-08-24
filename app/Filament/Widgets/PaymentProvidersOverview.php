<?php

namespace App\Filament\Widgets;

use App\Enums\Integration;
use App\Models\PaymentProvider;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
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
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New Payment Provider')
                    ->modalHeading('Add payment provider')
                    ->schema(static::formSchema())
                    ->action(function (array $data): void {
                        $paymentProvider = PaymentProvider::create([
                            'name' => $data['name'],
                            'address_1' => $data['address_1'],
                            'address_2' => $data['address_2'],
                            'county' => $data['county'],
                            'postcode' => $data['postcode'],
                        ]);

                        $paymentProvider->setProviderExternalId(Integration::Evertime, $data['payroll_provider_id'] ?? null);
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->fillForm(fn (PaymentProvider $record): array => [
                        'name' => $record->name,
                        'address_1' => $record->address_1,
                        'address_2' => $record->address_2,
                        'county' => $record->county,
                        'postcode' => $record->postcode,
                        'payroll_provider_id' => $record->providerExternalId(Integration::Evertime),
                    ])
                    ->schema(static::formSchema())
                    ->action(function (PaymentProvider $record, array $data): void {
                        $record->update([
                            'name' => $data['name'],
                            'address_1' => $data['address_1'],
                            'address_2' => $data['address_2'],
                            'county' => $data['county'],
                            'postcode' => $data['postcode'],
                        ]);

                        $record->setProviderExternalId(Integration::Evertime, $data['payroll_provider_id'] ?? null);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No payment providers yet')
            ->emptyStateDescription('Add the umbrella/Ltd companies your candidates are paid through here.');
    }

    /** @return array<int, TextInput> */
    private static function formSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('address_1')
                ->label('Address Line 1'),
            TextInput::make('address_2')
                ->label('Address Line 2'),
            TextInput::make('county'),
            TextInput::make('postcode'),
            TextInput::make('payroll_provider_id')
                ->label('Payroll Provider ID')
                ->helperText('This payment provider\'s existing ID in Evertime, if one already exists there.'),
        ];
    }
}
