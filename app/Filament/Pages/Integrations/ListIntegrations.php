<?php

namespace App\Filament\Pages\Integrations;

use App\Enums\Integration;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListIntegrations extends Page
{
    protected string $view = 'filament.pages.integrations.list-integrations';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Integrations';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Integrations';

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<int, array{provider: Integration, url: string, connected: bool}> */
    public function integrations(): array
    {
        $active = Auth::user()->company->payroll_provider;

        return collect(Integration::cases())
            ->map(fn (Integration $provider): array => [
                'provider' => $provider,
                'url' => EvertimeIntegration::getUrl(),
                'connected' => $active === $provider,
            ])
            ->all();
    }
}
