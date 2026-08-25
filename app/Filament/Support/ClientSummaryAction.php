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
        $address = collect([$client->address, $client->city, $client->postcode])
            ->filter()
            ->implode(', ');

        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('client_type')->label('Client Type')->state($client->clientType?->name)->placeholder('—'),
                    TextEntry::make('consultant')->label('Consultant')->state($client->consultant?->name)->placeholder('—'),
                    TextEntry::make('phone')->label('Phone')->state($client->phone)->placeholder('—'),
                    TextEntry::make('website')->label('Website')->state($client->website)->placeholder('—'),
                    TextEntry::make('address')->label('Address')->state($address ?: null)->placeholder('—')->columnSpanFull(),
                    TextEntry::make('county')->label('County')->state($client->county)->placeholder('—'),
                    TextEntry::make('key_stages')->label('Key Stages')->state(collect($client->key_stages ?? [])->implode(', ') ?: null)->placeholder('—'),
                    TextEntry::make('notes')->label('Notes')->state($client->notes)->placeholder('—')->columnSpanFull(),
                ]),
        ];
    }
}
