<?php

namespace App\Services\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

class EmailFooter
{
    private const string LOGO_CONTENT_ID = 'applebough-logo';

    public static function render(Company $company, ?User $consultant): string
    {
        $website = $company->website;

        return view('emails.partials.footer', [
            'company' => $company,
            'consultant' => $consultant,
            'companyPhone' => $company->phone,
            'companyWebsite' => $website ? self::withScheme($website) : null,
            'companyWebsiteLabel' => $website ? self::withoutScheme($website) : null,
            'logoContentId' => self::LOGO_CONTENT_ID,
        ])->render();
    }

    /**
     * The logo is embedded as a CID attachment rather than linked via a URL
     * (e.g. asset()) because that URL only resolves publicly if APP_URL is a
     * real internet-reachable domain — it silently breaks in local/staging
     * environments using a .test/internal hostname, and email clients can't
     * be relied on to fetch remote images at all anyway.
     *
     * @return array{name: string, path: string, mimeType: string, inline: bool, contentId: string}
     */
    public static function logoAttachment(): array
    {
        return [
            'name' => 'applebough-logo.png',
            'path' => public_path('images/applebough.png'),
            'mimeType' => 'image/png',
            'inline' => true,
            'contentId' => self::LOGO_CONTENT_ID,
        ];
    }

    private static function withScheme(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : "https://{$url}";
    }

    private static function withoutScheme(string $url): string
    {
        return Str::of($url)->after('://')->toString();
    }
}
