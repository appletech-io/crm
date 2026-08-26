<?php

namespace App\Filament\Support;

use App\Models\Client;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

/**
 * A small "quick view" icon button that slides over a read-only summary of
 * a client, wherever a client is shown as (or referenced by) a table row —
 * without disturbing that row's own click-through-to-edit behaviour. Accepts
 * a resolver so it can be dropped into a table whose $record IS the client
 * (e.g. the Clients list) or one where the client is just related to it
 * (e.g. a booking's client).
 *
 * The data behind every entry is computed by {@see data()}, a plain
 * directly-testable method, rather than inline inside the schema — mounting
 * an action in a Livewire test does not force its schema/state closures to
 * actually evaluate, so a bug inside one of them can pass every "mounts
 * without error" test and still 500 in the browser. Data computed this way
 * gets exercised directly by ordinary Pest assertions instead.
 */
class ClientSummaryAction
{
    public static function make(?Closure $resolveUsing = null): Action
    {
        $resolveClient = $resolveUsing ?? fn (Client $record): Client => $record;

        return Action::make('viewClientSummary')
            ->label('Quick view')
            ->tooltip('Quick view')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconButton()
            ->size(Size::Small)
            ->color('gray')
            ->visible(fn ($record): bool => (bool) $resolveClient($record))
            ->modalHeading(fn ($record): string => $resolveClient($record)->name)
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema(fn ($record) => self::schema($resolveClient($record)))
            ->extraModalFooterActions([
                Action::make('goToClientProfile')
                    ->label('Go to full profile')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn ($record): string => TodoLinkedRecord::clientLink($resolveClient($record))['url']),
            ]);
    }

    /** @return array<int, Component> */
    private static function schema(Client $client): array
    {
        $data = self::data($client);

        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('client_type')->label('Client Type')->state($data['client_type'])->placeholder('—'),
                    TextEntry::make('consultant')->label('Consultant')->state($data['consultant'])->placeholder('—'),
                    TextEntry::make('phone')->label('Phone')->state($data['phone'])->placeholder('—'),
                    TextEntry::make('website')->label('Website')->state($data['website'])->placeholder('—'),
                    TextEntry::make('address')->label('Address')->state($data['address'])->placeholder('—')->columnSpanFull(),
                    TextEntry::make('county')->label('County')->state($data['county'])->placeholder('—'),
                    TextEntry::make('key_stages')->label('Key Stages')->state($data['key_stages'])->placeholder('—'),
                    TextEntry::make('vacancies')->label('Vacancies')->badge()->state($data['vacancies_label'])->color($data['vacancies_color']),
                    TextEntry::make('notes')->label('Notes')->state($data['notes'])->placeholder('—')->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array{
     *     client_type: ?string,
     *     consultant: ?string,
     *     phone: ?string,
     *     website: ?string,
     *     address: ?string,
     *     county: ?string,
     *     key_stages: ?string,
     *     vacancies_label: string,
     *     vacancies_color: string,
     *     notes: ?string,
     * }
     */
    public static function data(Client $client): array
    {
        $totalVacancies = $client->vacancies()->count();
        $openVacancies = $client->vacancies()->whereHas('jobStatus', fn ($query) => $query->where('is_filled_status', false))->count();

        return [
            'client_type' => $client->clientType?->name,
            'consultant' => $client->consultant?->name,
            'phone' => $client->phone,
            'website' => $client->website,
            'address' => collect([$client->address, $client->city, $client->postcode])->filter()->implode(', ') ?: null,
            'county' => $client->county,
            'key_stages' => collect($client->key_stages ?? [])->implode(', ') ?: null,
            'vacancies_label' => $totalVacancies > 0 ? "{$openVacancies} open / {$totalVacancies} total" : 'None yet',
            'vacancies_color' => $openVacancies > 0 ? 'success' : 'gray',
            'notes' => $client->notes,
        ];
    }
}
