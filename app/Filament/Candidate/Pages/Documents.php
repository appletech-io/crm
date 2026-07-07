<?php

namespace App\Filament\Candidate\Pages;

use App\Models\EducationCandidate;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Documents extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.candidate.pages.documents';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $title = 'Documents';

    /** @return array<string, array{label: string, description: string, uploaded: bool}> */
    public function documentTypes(): array
    {
        $candidate = $this->candidate();

        $types = [
            'cv' => [
                'label' => 'CV',
                'description' => 'Your most recent CV.',
                'uploaded' => (bool) $candidate->application?->cv_temp_path,
            ],
            'photo' => [
                'label' => 'Photo',
                'description' => 'A clear, recent photo of yourself.',
                'uploaded' => (bool) $candidate->photo_path,
            ],
            'prevent_training' => [
                'label' => 'Prevent Training',
                'description' => 'Certificate confirming completion of Prevent duty training.',
                'uploaded' => false,
            ],
            'safeguarding_training' => [
                'label' => 'Safeguarding Training',
                'description' => 'Certificate confirming completion of safeguarding training.',
                'uploaded' => false,
            ],
            'proof_of_address' => [
                'label' => 'Proof of Address',
                'description' => 'A recent utility bill or bank statement.',
                'uploaded' => false,
            ],
        ];

        match ($candidate->right_to_work_type) {
            'birth_certificate' => $types['birth_certificate'] = [
                'label' => 'Birth Certificate',
                'description' => 'Your birth certificate, as proof of your right to work.',
                'uploaded' => false,
            ],
            'passport' => $types['passport'] = [
                'label' => 'Passport',
                'description' => 'Your passport, as proof of your right to work.',
                'uploaded' => false,
            ],
            default => null,
        };

        if ($candidate->has_dbs === 'yes') {
            $types['dbs'] = [
                'label' => 'DBS',
                'description' => 'Your DBS certificate or update service check.',
                'uploaded' => false,
            ];
        } elseif ($candidate->has_dbs === 'no') {
            $types['proof_of_address_2'] = [
                'label' => 'Proof of Address 2',
                'description' => 'A second, different proof of address, since you do not currently have a DBS.',
                'uploaded' => false,
            ];
        }

        if ($candidate->has_naric === 'yes') {
            $types['uk_naric'] = [
                'label' => 'UK NARIC',
                'description' => 'Your UK NARIC statement of comparability.',
                'uploaded' => false,
            ];
        }

        return $types;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->documentTypes())
            ->recordClasses(fn (array $record): ?string => $record['uploaded'] ? null : 'candidate-document-row-missing')
            ->columns([
                TextColumn::make('label')
                    ->label('Document'),

                TextColumn::make('description')
                    ->label('Description')
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('uploaded')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Not uploaded')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('preventTrainingInfo')
                    ->label('')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->tooltip('More information')
                    ->visible(fn (array $record): bool => $record['label'] === 'Prevent Training')
                    ->modalHeading('Prevent Training')
                    ->modalContent(view('filament.candidate.pages.documents.prevent-training-info'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('upload')
                    ->label('Upload')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn (array $record): bool => ! $record['uploaded']),

                Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (array $record): bool => $record['uploaded']),

                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (array $record): bool => $record['uploaded']),
            ]);
    }

    private function candidate(): EducationCandidate
    {
        /** @var EducationCandidate $candidate */
        $candidate = auth()->user()->candidate;

        return $candidate;
    }
}
