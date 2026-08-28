<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Filament\Resources\Users\UserResource;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    use HasPayrollProviderErrorAlert;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryPayrollSync')
                ->label('Retry Payroll Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->hasProviderError($this->record))
                ->action(function (): void {
                    /** @var User $record */
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
            DeleteAction::make(),
        ];
    }

    /**
     * Setting a new password for a user on their behalf should require them
     * to change it again, the same as a freshly created user would.
     *
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('password', $data)) {
            $data['password_changed_at'] = null;
            $data['requires_account_setup'] = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Only admins can see the Payroll Provider ID field (see the form's
        // matching isAdmin() gate) — this mirrors it so a non-admin saving
        // the rest of the form, where the field was never rendered, can't
        // wipe out an existing external ID mapping via its absent state.
        if (! (Auth::user()?->isAdmin() ?? false)) {
            return;
        }

        $provider = Auth::user()->company->payroll_provider;

        if ($provider) {
            $this->record->setProviderExternalId($provider, $this->form->getRawState()['payroll_provider_id'] ?? null);
        }
    }
}
