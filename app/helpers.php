<?php

if (! function_exists('active_industry')) {
    function active_industry(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Cache::get("user.{$user->id}.active_industry");
    }
}

if (! function_exists('active_industry_id')) {
    function active_industry_id(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Cache::get("user.{$user->id}.active_industry_id");
    }
}
