<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EducationVetting\Tables\VettingTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * A single "bucket" of candidates still in vetting (not complete / mostly complete /
 * almost complete), configured per-instance via {@see WidgetConfiguration}
 * so the same table widget can be reused for every sector and bucket rather than needing
 * a subclass for each combination.
 */
class ComplianceVettingTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    /** @var class-string<Model> */
    public string $candidateModelClass = '';

    /** @var class-string<\Filament\Resources\Resource> */
    public string $vettingResourceClass = '';

    /** @var array<int, string> */
    public array $stepLabelsList = [];

    public int $stepFrom = 1;

    public int $stepTo = 1;

    public string $bucketHeading = '';

    /**
     * Matches the colours used for the "Vetting Progress" badge on the Compliance
     * tab ({@see VettingTable::vettingProgressColor()}).
     */
    public string $bucketColor = 'gray';

    /** @return array<int, array{from: int, to: int, heading: string, color: string}> */
    public static function buckets(int $totalSteps): array
    {
        $lowerBound = (int) ceil($totalSteps / 3);
        $upperBound = (int) ceil(2 * $totalSteps / 3);

        return [
            ['from' => 1, 'to' => $lowerBound, 'heading' => 'Not Complete', 'color' => 'danger'],
            ['from' => $lowerBound + 1, 'to' => $upperBound, 'heading' => 'Mostly Complete', 'color' => 'warning'],
            ['from' => $upperBound + 1, 'to' => $totalSteps, 'heading' => 'Almost Complete', 'color' => 'info'],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->bucketQuery())
            ->heading($this->coloredHeading())
            ->columns([
                TextColumn::make('first_name')
                    ->label('Name')
                    ->getStateUsing(fn (Model $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->url(fn (Model $record): string => $this->vettingUrl($record)),
                TextColumn::make('compliance_step')
                    ->label('Step')
                    ->badge()
                    ->color($this->bucketColor)
                    ->formatStateUsing(fn (Model $record): string => $this->stepLabel($record))
                    ->sortable(),
            ])
            ->defaultSort('created_at')
            ->paginated([5, 10, 25]);
    }

    protected function coloredHeading(): Htmlable
    {
        return new HtmlString(Blade::render(
            '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
            ['color' => $this->bucketColor, 'label' => "{$this->bucketHeading} ({$this->bucketQuery()->count()})"],
        ));
    }

    protected function bucketQuery(): Builder
    {
        $candidateModelClass = $this->candidateModelClass;

        return $candidateModelClass::query()
            ->whereHas('statuses.status', fn (Builder $query) => $query->where('name', 'Vetting'))
            ->whereNull('compliance_completed_at')
            ->whereRaw('coalesce(compliance_step, 1) between ? and ?', [$this->stepFrom, $this->stepTo]);
    }

    public function stepLabel(Model $candidate): string
    {
        $totalSteps = count($this->stepLabelsList);
        $stepNumber = min($candidate->compliance_step ?? 1, $totalSteps);

        return "Step {$stepNumber} of {$totalSteps}: ".$this->stepLabelsList[$stepNumber - 1];
    }

    public function vettingUrl(Model $candidate): string
    {
        $resourceClass = $this->vettingResourceClass;

        return $resourceClass::getUrl('edit', ['record' => $candidate]);
    }
}
