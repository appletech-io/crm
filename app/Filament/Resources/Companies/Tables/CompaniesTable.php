<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('industries.name')
                    ->label('Sectors')
                    ->badge()
                    ->separator(','),
                TextColumn::make('email_provider')
                    ->label('Email Provider')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                Action::make('viewAs')
                    ->label('View As')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->schema(fn (Company $record): array => [
                        Select::make('user_id')
                            ->label('View as')
                            ->options(fn (): array => self::eligibleUsers($record)
                                ->mapWithKeys(fn (User $user): array => [
                                    $user->id => "{$user->name} (".($user->hasRole('admin') ? 'Admin' : 'Consultant').')',
                                ])
                                ->toArray())
                            ->required()
                            ->searchable(),
                    ])
                    ->modalHeading(fn (Company $record): string => "View {$record->name} as")
                    ->modalSubmitActionLabel('View As')
                    ->disabled(fn (Company $record): bool => self::eligibleUsers($record)->isEmpty())
                    ->tooltip(fn (Company $record): ?string => self::eligibleUsers($record)->isEmpty()
                        ? 'This company has no admin or consultant users yet.'
                        : null)
                    ->action(function (Company $record, array $data) {
                        $targetUser = self::eligibleUsers($record)->firstWhere('id', $data['user_id']);

                        abort_unless($targetUser, 403);

                        $originalUserId = auth()->id();
                        Auth::login($targetUser);
                        session(['impersonator_id' => $originalUserId]);

                        return redirect('/crm');
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return Collection<int, User> */
    private static function eligibleUsers(Company $record): Collection
    {
        return User::withoutGlobalScope('company')
            ->where('company_id', $record->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'consultant']))
            ->orderBy('name')
            ->get();
    }
}
