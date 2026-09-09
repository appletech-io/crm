<?php

namespace App\Filament\Resources\SampleProfiles\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SampleProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Sample Profile')
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->disk(config('filesystems.default'))
                ->directory(fn (): string => 'sample-profiles/'.Auth::user()->company_id.'/'.active_industry_id())
                ->preserveFilenames()
                ->maxSize(10240)
                ->required()
                ->helperText('PDF or Word (.docx). Up to 5 per sector — these set the tone and style the AI writes new candidate profiles in.')
                ->columnSpanFull(),
        ]);
    }
}
