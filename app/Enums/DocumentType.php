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
    case Dbs = 'dbs';
    case UkNaric = 'uk_naric';

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
            self::Dbs => 'DBS',
            self::UkNaric => 'UK NARIC',
        };
    }
}
