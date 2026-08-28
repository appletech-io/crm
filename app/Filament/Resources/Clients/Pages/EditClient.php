<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\EmailTemplateAudience;
use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\SendCustomEmailAction;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClient extends EditRecord
{
    use HasPayrollProviderErrorAlert;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendCustomEmailAction::header(EmailTemplateAudience::Client),
            Action::make('retryPayrollSync')
                ->label('Retry Payroll Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->hasProviderError($this->record))
                ->action(function (): void {
                    /** @var Client $record */
                    $record = $this->record;

                    try {
                        SyncPayrollProviderRecord::dispatchSync($record);
                    } catch (\Throwable) {
                        // recordFailure() inside the job already persisted
                        // the error detail — the check below picks it up.
                    }

                    if ($this->hasProviderError($record)) {
                        Notification::make()
                            ->danger()
                            ->title('Retry failed — see the error below')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Payroll sync retried successfully')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            ForceDeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            RestoreAction::make(),
        ];
    }

    /**
     * Only admins can see the Payroll Provider ID field (ClientForm gates it
     * on isAdmin() too) — this mirrors that gate so a consultant saving the
     * rest of the form, where the field was never rendered, can't wipe out
     * an existing external ID mapping via its absent/blank raw state.
     */
    protected function afterSave(): void
    {
        if (! (Auth::user()?->isAdmin() ?? false)) {
            return;
        }

        $provider = Auth::user()->company->payroll_provider;

        if ($provider) {
            $this->record->setProviderExternalId($provider, $this->form->getRawState()['payroll_provider_id'] ?? null);
        }
    }
}
