<?php

namespace App\Filament\Support;

use App\Enums\EmailTemplateAudience;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPool;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\MarketingCampaign;
use App\Services\Mail\CustomTemplatePlaceholders;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the "pick a Custom template (or write a one-off email), send"
 * action shared by the Education/Healthcare candidate and Client resources'
 * row, bulk, and edit header actions — parameterized only by which audience
 * of templates it should offer.
 */
class SendCustomEmailAction
{
    public static function record(EmailTemplateAudience $audience): Action
    {
        return Action::make('sendEmail')
            ->label('Send Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalSubmitActionLabel('Send')
            ->schema(fn (): array => static::formSchema($audience))
            ->action(function (array $data, EducationCandidate|HealthcareCandidate|Client $record): void {
                $recipient = static::resolveRecipient($record);

                if (blank($recipient['email'])) {
                    Notification::make()
                        ->danger()
                        ->title('Cannot send — no contact email on file')
                        ->send();

                    return;
                }

                SendCustomTemplateEmail::dispatch(
                    static::templateFromData($data),
                    $record,
                    auth()->id(),
                    $data['adhoc_subject'] ?? null,
                    $data['adhoc_body'] ?? null,
                );

                Notification::make()->success()->title('Email queued for sending')->send();
            });
    }

    public static function bulk(EmailTemplateAudience $audience): BulkAction
    {
        return BulkAction::make('sendEmail')
            ->label('Send Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalSubmitActionLabel('Send')
            ->schema(fn (): array => static::formSchema($audience))
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Collection $records): void {
                $template = static::templateFromData($data);

                [$sendable, $skipped] = $records->partition(
                    fn (EducationCandidate|HealthcareCandidate|Client $record): bool => filled(static::resolveRecipient($record)['email'])
                );

                $sendable->each(fn (EducationCandidate|HealthcareCandidate|Client $record) => SendCustomTemplateEmail::dispatch(
                    $template,
                    $record,
                    auth()->id(),
                    $data['adhoc_subject'] ?? null,
                    $data['adhoc_body'] ?? null,
                ));

                $title = "Queued {$sendable->count()} email(s)";

                if ($skipped->isNotEmpty()) {
                    $names = $skipped
                        ->map(fn (EducationCandidate|HealthcareCandidate|Client $record): string => $record instanceof Client
                            ? ($record->name ?? '')
                            : trim("{$record->first_name} {$record->last_name}"))
                        ->implode(', ');

                    $title .= ". Skipped {$skipped->count()} (no contact email on file): {$names}";
                }

                Notification::make()->title($title)->send();
            });
    }

    public static function header(EmailTemplateAudience $audience): Action
    {
        return static::record($audience);
    }

    public static function campaign(MarketingCampaign $campaign): Action
    {
        return Action::make('sendCampaignEmail')
            ->label('Send Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalSubmitActionLabel('Send')
            ->schema(fn (): array => static::formSchema(EmailTemplateAudience::Client))
            ->action(function (array $data) use ($campaign): void {
                $template = static::templateFromData($data);

                [$sendable, $skipped] = $campaign->clients->partition(
                    fn (Client $client): bool => filled(static::resolveRecipient($client)['email'])
                );

                $sendable->each(fn (Client $client) => SendCustomTemplateEmail::dispatch(
                    $template,
                    $client,
                    auth()->id(),
                    $data['adhoc_subject'] ?? null,
                    $data['adhoc_body'] ?? null,
                    $campaign,
                ));

                $title = "Queued {$sendable->count()} email(s)";

                if ($skipped->isNotEmpty()) {
                    $names = $skipped->map(fn (Client $client): string => $client->name ?? '')->implode(', ');

                    $title .= ". Skipped {$skipped->count()} (no contact email on file): {$names}";
                }

                Notification::make()->title($title)->send();
            });
    }

    public static function pool(ClientPool $pool): Action
    {
        return Action::make('sendPoolEmail')
            ->label('Send Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalSubmitActionLabel('Send')
            ->schema(fn (): array => [
                ...static::formSchema(EmailTemplateAudience::Client),
                Select::make('marketing_campaign_id')
                    ->label('Track under campaign (optional)')
                    ->options(fn (): array => MarketingCampaign::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()),
            ])
            ->action(function (array $data) use ($pool): void {
                $template = static::templateFromData($data);
                $campaign = filled($data['marketing_campaign_id'] ?? null)
                    ? MarketingCampaign::find($data['marketing_campaign_id'])
                    : null;

                [$sendable, $skipped] = $pool->clients->partition(
                    fn (Client $client): bool => filled(static::resolveRecipient($client)['email'])
                );

                $sendable->each(fn (Client $client) => SendCustomTemplateEmail::dispatch(
                    $template,
                    $client,
                    auth()->id(),
                    $data['adhoc_subject'] ?? null,
                    $data['adhoc_body'] ?? null,
                    $campaign,
                ));

                $title = "Queued {$sendable->count()} email(s)";

                if ($skipped->isNotEmpty()) {
                    $names = $skipped->map(fn (Client $client): string => $client->name ?? '')->implode(', ');

                    $title .= ". Skipped {$skipped->count()} (no contact email on file): {$names}";
                }

                Notification::make()->title($title)->send();
            });
    }

    private static function templateFromData(array $data): ?EmailTemplate
    {
        return ($data['mode'] ?? 'template') === 'template'
            ? EmailTemplate::findOrFail($data['email_template_id'])
            : null;
    }

    /** @return array<int, Component> */
    private static function formSchema(EmailTemplateAudience $audience): array
    {
        return [
            Radio::make('mode')
                ->label('Send')
                ->options([
                    'template' => 'A saved template',
                    'adhoc' => 'A one-off email',
                ])
                ->default('template')
                ->inline()
                ->live()
                ->required(),

            Select::make('email_template_id')
                ->label('Template')
                ->options(fn (): array => EmailTemplate::query()
                    ->custom()
                    ->forAudience($audience)
                    ->where('company_id', auth()->user()->company_id)
                    ->where('industry_id', active_industry_id())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->visible(fn (Get $get): bool => ($get('mode') ?? 'template') === 'template')
                ->required(fn (Get $get): bool => ($get('mode') ?? 'template') === 'template'),

            TextInput::make('adhoc_subject')
                ->label('Subject')
                ->visible(fn (Get $get): bool => $get('mode') === 'adhoc')
                ->required(fn (Get $get): bool => $get('mode') === 'adhoc'),

            RichEditor::make('adhoc_body')
                ->label('Body')
                ->visible(fn (Get $get): bool => $get('mode') === 'adhoc')
                ->required(fn (Get $get): bool => $get('mode') === 'adhoc')
                ->helperText('You can use: '.collect(CustomTemplatePlaceholders::definitions($audience))
                    ->keys()
                    ->map(fn (string $key): string => "{{$key}}")
                    ->implode(', ')),
        ];
    }

    /** @return array{email: ?string, contact: ?ClientContact} */
    private static function resolveRecipient(EducationCandidate|HealthcareCandidate|Client $record): array
    {
        if ($record instanceof Client) {
            $contact = $record->bookingContact();

            return ['email' => $contact?->email, 'contact' => $contact];
        }

        return ['email' => $record->email, 'contact' => null];
    }
}
