<?php

namespace App\Services\Mail\Concerns;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;

/**
 * Shared by every job that sends an {@see EmailTemplate}-backed
 * email. Each job is still responsible for building its own replacements
 * array (the data it has available differs too much to generalize further),
 * but the mechanical substitution — and the `{key}` bracket convention —
 * lives here once. The valid keys for a given template are declared on
 * {@see EmailTemplateType::placeholders()}.
 */
trait ReplacesEmailPlaceholders
{
    /**
     * @param  array<string, string>  $replacements  Keys without braces, e.g. ['candidate_name' => 'Jane Doe']
     */
    protected static function replacePlaceholders(string $content, array $replacements): string
    {
        $search = array_map(fn (string $key): string => '{'.$key.'}', array_keys($replacements));

        return str_replace($search, array_values($replacements), $content);
    }
}
