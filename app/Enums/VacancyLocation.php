<?php

namespace App\Enums;

enum VacancyLocation: string
{
    case AcocksGreen = 'Acocks Green';
    case AlumRock = 'Alum Rock';
    case AshbyDeLaZouch = 'Ashby-de-la-Zouch';
    case Aston = 'Aston';
    case BalsallHeath = 'Balsall Heath';
    case Banbury = 'Banbury';
    case BartleyGreen = 'Bartley Green';
    case Bedworth = 'Bedworth';
    case Billesley = 'Billesley';
    case Bilston = 'Bilston';
    case Birmingham = 'Birmingham';
    case BordesleyGreen = 'Bordesley Green';
    case Bournville = 'Bournville';
    case BrierleyHill = 'Brierley Hill';
    case Bromsgrove = 'Bromsgrove';
    case CampHill = 'Camp Hill';
    case CastleBromwich = 'Castle Bromwich';
    case CastleVale = 'Castle Vale';
    case ChelmsleyWood = 'Chelmsley Wood';
    case Clent = 'Clent';
    case Coalville = 'Coalville';
    case Coseley = 'Coseley';
    case Cotteridge = 'Cotteridge';
    case Coventry = 'Coventry';
    case CradleyHeath = 'Cradley Heath';
    case Daventry = 'Daventry';
    case DruidsHeath = 'Druids Heath';
    case Dudley = 'Dudley';
    case Edgbaston = 'Edgbaston';
    case Erdington = 'Erdington';
    case FourOaks = 'Four Oaks';
    case Frankley = 'Frankley';
    case GreatBarr = 'Great Barr';
    case Halesowen = 'Halesowen';
    case HallGreen = 'Hall Green';
    case Handsworth = 'Handsworth';
    case HandsworthWood = 'Handsworth Wood';
    case Harborne = 'Harborne';
    case Hednesford = 'Hednesford';
    case HenleyInArden = 'Henley in Arden';
    case Highgate = 'Highgate';
    case Hinckley = 'Hinckley';
    case Hockley = 'Hockley';
    case HodgeHill = 'Hodge Hill';
    case Hollywood = 'Hollywood';
    case Ibstock = 'Ibstock';
    case Kenilworth = 'Kenilworth';
    case Kidderminster = 'Kidderminster';
    case Kineton = 'Kineton';
    case KingsHeath = 'Kings Heath';
    case KingsNorton = 'Kings Norton';
    case Kingstanding = 'Kingstanding';
    case Kingswinford = 'Kingswinford';
    case Kinver = 'Kinver';
    case KittsGreen = 'Kitts Green';
    case Ladywood = 'Ladywood';
    case LeamingtonSpa = 'Leamington Spa';
    case Leicester = 'Leicester';
    case Loughborough = 'Loughborough';
    case Lozells = 'Lozells';
    case Lutterworth = 'Lutterworth';
    case MarketHarborough = 'Market Harborough';
    case Markfield = 'Markfield';
    case MeltonMowbray = 'Melton Mowbray';
    case Moseley = 'Moseley';
    case Nechells = 'Nechells';
    case NewTown = 'New Town';
    case Northampton = 'Northampton';
    case Nottingham = 'Nottingham';
    case Nuneaton = 'Nuneaton';
    case Oldbury = 'Oldbury';
    case PerryBarr = 'Perry Barr';
    case PypeHayes = 'Pype Hayes';
    case Quinton = 'Quinton';
    case Quorn = 'Quorn';
    case RowleyRegis = 'Rowley Regis';
    case Rubery = 'Rubery';
    case Rugby = 'Rugby';
    case Saltley = 'Saltley';
    case SellyOak = 'Selly Oak';
    case ShardEnd = 'Shard End';
    case Sheldon = 'Sheldon';
    case SmallHeath = 'Small Heath';
    case Smethwick = 'Smethwick';
    case Solihull = 'Solihull';
    case Sparkbrook = 'Sparkbrook';
    case Sparkhill = 'Sparkhill';
    case Stechford = 'Stechford';
    case Stourbridge = 'Stourbridge';
    case StratfordUponAvon = 'Stratford-upon-Avon';
    case SuttonColdfield = 'Sutton Coldfield';
    case Tamworth = 'Tamworth';
    case Tipton = 'Tipton';
    case Tividale = 'Tividale';
    case TurvesGreen = 'Turves Green';
    case Tyseley = 'Tyseley';
    case Walsall = 'Walsall';
    case Warwick = 'Warwick';
    case WashwoodHeath = 'Washwood Heath';
    case Wednesbury = 'Wednesbury';
    case WestBromwich = 'West Bromwich';
    case WestHeath = 'West Heath';
    case Wigston = 'Wigston';
    case Willenhall = 'Willenhall';
    case Witton = 'Witton';
    case Wolverhampton = 'Wolverhampton';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
