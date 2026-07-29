<?php

namespace App\Services;

class ReferenceAccessSession
{
    public static function hasVerified(string $token): bool
    {
        return (bool) session(self::key($token));
    }

    public static function markVerified(string $token): void
    {
        session([self::key($token) => true]);
    }

    private static function key(string $token): string
    {
        return "reference.{$token}.verified";
    }
}
