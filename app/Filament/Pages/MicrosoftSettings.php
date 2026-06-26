<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class MicrosoftSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.microsoft-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Microsoft / Outlook';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public function mount(): void
    {
        $company = Auth::user()->company;

        $this->form->fill([
            'ms_tenant_id' => $company->ms_tenant_id,
            'ms_client_id' => $company->ms_client_id,
            'ms_client_secret' => $company->ms_client_secret,
            'ms_sender_email' => $company->ms_sender_email,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
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
                    ->required(),
                TextInput::make('ms_sender_email')
                    ->label('Sender Email')
                    ->helperText('The mailbox emails will be sent from (must exist in your tenant)')
                    ->email()
                    ->required(),
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Auth::user()->company->update($data);

        Notification::make()
            ->title('Microsoft settings saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
