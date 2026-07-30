<?php

namespace App\Filament\Support;

use App\Enums\EmailTemplateAudience;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\CustomTemplatePlaceholders;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Builds the "pick a Custom template, preview it, send" action shared by the
 * Education/Healthcare candidate and Client resources' row, bulk, and edit
 * header actions — parameterized only by which audience of templates it
 * should offer.
 */
class SendCustomEmailAction
{
    use ReplacesEmailPlaceholders;

    public static function record(EmailTemplateAudience $audience): Action
    {
        return Action::make('sendEmail')
            ->label('Send Email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalSubmitActionLabel('Send')
            ->schema(fn (EducationCandidate|HealthcareCandidate|Client $record): array => static::formSchema($audience, $record))
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
                    EmailTemplate::findOrFail($data['email_template_id']),
                    $record,
                    auth()->id(),
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
            ->schema(fn (Collection $records): array => static::formSchema($audience, $records->first(), $records->count()))
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Collection $records): void {
                $template = EmailTemplate::findOrFail($data['email_template_id']);

                [$sendable, $skipped] = $records->partition(
                    fn (EducationCandidate|HealthcareCandidate|Client $record): bool => filled(static::resolveRecipient($record)['email'])
                );

                $sendable->each(fn (EducationCandidate|HealthcareCandidate|Client $record) => SendCustomTemplateEmail::dispatch($template, $record, auth()->id()));

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

    /** @return array<int, Component> */
    private static function formSchema(EmailTemplateAudience $audience, EducationCandidate|HealthcareCandidate|Client|null $sampleRecord, int $recipientCount = 1): array
    {
        return [
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
                ->live()
                ->required(),

            Placeholder::make('preview')
                ->label('Preview')
                ->content(fn (Get $get): HtmlString => static::previewContent($get('email_template_id'), $sampleRecord, $recipientCount)),
        ];
    }

    private static function previewContent(?string $templateId, EducationCandidate|HealthcareCandidate|Client|null $sampleRecord, int $recipientCount): HtmlString
    {
        if (blank($templateId) || ! $sampleRecord) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Select a template to preview it.</p>');
        }

        $template = EmailTemplate::find($templateId);

        if (! $template) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Select a template to preview it.</p>');
        }

        $recipient = static::resolveRecipient($sampleRecord);

        if (blank($recipient['email'])) {
            return new HtmlString('<p class="text-sm text-danger-600 dark:text-danger-400">This record has no contact email on file — it will be skipped.</p>');
        }

        $replacements = CustomTemplatePlaceholders::resolve($sampleRecord, $recipient['contact']);
        $subject = static::replacePlaceholders($template->subject ?? '', $replacements);
        $body = static::replacePlaceholders($template->body ?? '', $replacements);

        $note = $recipientCount > 1
            ? '<p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Previewing for '.e($recipient['email']).' — each of the '.$recipientCount.' recipients gets their own personalized version.</p>'
            : '';

        return new HtmlString(
            $note
            .'<p class="mb-1 text-sm"><strong>Subject:</strong> '.e($subject).'</p>'
            .'<div class="prose prose-sm dark:prose-invert max-w-none rounded border border-gray-200 p-3 dark:border-gray-700">'.$body.'</div>'
        );
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
