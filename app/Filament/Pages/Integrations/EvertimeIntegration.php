<?php

namespace App\Filament\Pages\Integrations;

use App\Enums\Integration;
use App\Filament\Widgets\PaymentProvidersOverview;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EvertimeIntegration extends Page
{
    protected string $view = 'filament.pages.integrations.evertime-integration';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Evertime';

    protected static ?string $slug = 'integrations/evertime';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $company = Auth::user()->company;

        $this->form->fill([
            'enabled' => $company->payroll_provider === Integration::Evertime,
            'api_url' => $company->integrationSetting(Integration::Evertime, 'api_url'),
            'api_key' => $company->integrationSetting(Integration::Evertime, 'api_key'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Integrations')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ListIntegrations::getUrl()),

            Action::make('save')
                ->label('Save')
                ->action(fn () => $this->save()),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Tabs')
                ->tabs([
                    Tab::make('Connection')
                        ->schema([
                            Section::make()
                                ->columns(2)
                                ->schema([
                                    Toggle::make('enabled')
                                        ->label('Enabled')
                                        ->helperText('Turn the integration off to stop sending timesheets to Evertime without losing the saved connection details below.')
                                        ->columnSpanFull(),
                                    TextInput::make('api_url')
                                        ->label('API URL')
                                        ->helperText('Evertime\'s root API URL, e.g. their staging or production environment.')
                                        ->url()
                                        ->required(),
                                    TextInput::make('api_key')
                                        ->label('API Key')
                                        ->password()
                                        ->revealable()
                                        ->dehydrated(fn (?string $state): bool => filled($state))
                                        ->required(fn (): bool => blank(Auth::user()->company->integrationSetting(Integration::Evertime, 'api_key'))),
                                ]),
                        ]),

                    Tab::make('Payment Providers')
                        ->schema([
                            LivewireComponent::make(PaymentProvidersOverview::class)
                                ->key('payment-providers-overview'),
                        ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var Company $company */
        $company = Auth::user()->company;

        // Only this company's own toggle controls whether Evertime is the
        // active payroll_provider — disabling it clears that flag (so
        // SendTimesheetToPayrollProvider's payrollProvider() lookup returns
        // null and nothing gets sent) without touching the stored api_url/
        // api_key, so re-enabling later doesn't require re-entering them.
        $company->update([
            'payroll_provider' => ($data['enabled'] ?? false) ? Integration::Evertime : null,
        ]);

        $company->setIntegrationSetting(Integration::Evertime, 'api_url', $data['api_url']);

        if (filled($data['api_key'] ?? null)) {
            $company->setIntegrationSetting(Integration::Evertime, 'api_key', $data['api_key']);
        }

        Notification::make()
            ->success()
            ->title('Evertime connection saved')
            ->send();
    }
}
