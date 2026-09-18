<?php

namespace App\Enums;

/**
 * A curated set of icons a user can pick for one of their own quick links —
 * kept to a fixed list (rather than a free-text icon name) so a typo can
 * never leave a quick link with a broken/blank icon.
 */
enum QuickLinkIcon: string
{
    case Briefcase = 'heroicon-o-briefcase';
    case BuildingOffice = 'heroicon-o-building-office-2';
    case BuildingLibrary = 'heroicon-o-building-library';
    case AcademicCap = 'heroicon-o-academic-cap';
    case Heart = 'heroicon-o-heart';
    case UserGroup = 'heroicon-o-user-group';
    case CalendarDays = 'heroicon-o-calendar-days';
    case ClipboardDocumentList = 'heroicon-o-clipboard-document-list';
    case CheckCircle = 'heroicon-o-check-circle';
    case ChartBar = 'heroicon-o-chart-bar';
    case CurrencyPound = 'heroicon-o-currency-pound';
    case DocumentText = 'heroicon-o-document-text';
    case Envelope = 'heroicon-o-envelope';
    case Phone = 'heroicon-o-phone';
    case Flag = 'heroicon-o-flag';
    case Bookmark = 'heroicon-o-bookmark';
    case Star = 'heroicon-o-star';
    case MagnifyingGlass = 'heroicon-o-magnifying-glass';

    public function label(): string
    {
        return match ($this) {
            self::Briefcase => 'Briefcase',
            self::BuildingOffice => 'Office building',
            self::BuildingLibrary => 'Library building',
            self::AcademicCap => 'Academic cap',
            self::Heart => 'Heart',
            self::UserGroup => 'People',
            self::CalendarDays => 'Calendar',
            self::ClipboardDocumentList => 'Clipboard',
            self::CheckCircle => 'Check circle',
            self::ChartBar => 'Chart',
            self::CurrencyPound => 'Currency',
            self::DocumentText => 'Document',
            self::Envelope => 'Envelope',
            self::Phone => 'Phone',
            self::Flag => 'Flag',
            self::Bookmark => 'Bookmark',
            self::Star => 'Star',
            self::MagnifyingGlass => 'Magnifying glass',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
