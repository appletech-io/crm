<?php

namespace App\Enums;

enum DocumentType: string
{
    case Cv = 'cv';
    case Photo = 'photo';
    case PreventTraining = 'prevent_training';
    case SafeguardingTraining = 'safeguarding_training';
    case ProofOfAddress = 'proof_of_address';
    case ProofOfAddressTwo = 'proof_of_address_2';
    case ProofOfNi = 'proof_of_ni';
    case BirthCertificate = 'birth_certificate';
    case Passport = 'passport';
    case DbsFront = 'dbs_front';
    case DbsBack = 'dbs_back';
    case UkNaric = 'uk_naric';
    case ProfessionalRegistration = 'professional_registration';

    public function label(): string
    {
        return match ($this) {
            self::Cv => 'CV',
            self::Photo => 'Photo',
            self::PreventTraining => 'Prevent Training',
            self::SafeguardingTraining => 'Safeguarding Training',
            self::ProofOfAddress => 'Proof of Address',
            self::ProofOfAddressTwo => 'Proof of Address 2',
            self::ProofOfNi => 'Proof of NI',
            self::BirthCertificate => 'Birth Certificate',
            self::Passport => 'Passport',
            self::DbsFront => 'DBS (Front)',
            self::DbsBack => 'DBS (Back)',
            self::UkNaric => 'UK NARIC',
            self::ProfessionalRegistration => 'Professional Registration Certificate',
        };
    }
}
