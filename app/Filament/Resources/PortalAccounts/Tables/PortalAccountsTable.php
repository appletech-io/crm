<?php

namespace App\Filament\Resources\PortalAccounts\Tables;

use App\Filament\Support\CandidateSummaryAction;
use App\Filament\Support\ClientSummaryAction;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class PortalAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->state(fn (User $record): string => $record->candidate_id ? 'Candidate' : 'Client')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Candidate' ? 'info' : 'success'),
                TextColumn::make('linked_to')
                    ->label('Linked To')
                    ->state(function (User $record): ?string {
                        $candidate = $record->candidate;

                        if ($candidate instanceof EducationCandidate || $candidate instanceof HealthcareCandidate) {
                            return trim("{$candidate->first_name} {$candidate->last_name}");
                        }

                        return $record->client()?->name;
                    }),
            ])
            ->recordActions([
                CandidateSummaryAction::make(fn (User $record) => $record->candidate instanceof EducationCandidate || $record->candidate instanceof HealthcareCandidate
                    ? $record->candidate
                    : null),
                ClientSummaryAction::make(fn (User $record) => $record->client()),
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->modalHeading(fn (User $record): string => "Reset password for {$record->name}")
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => $data['password'],
                            'password_changed_at' => null,
                            'requires_account_setup' => true,
                        ]);
                    })
                    ->successNotificationTitle('Password reset'),
            ]);
    }
}
