<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Response;

class CompanyLogoController extends Controller
{
    public function show(Company $company): Response
    {
        return response($company->logoContents())
            ->header('Content-Type', $company->logoMimeType())
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function favicon(Company $company): Response
    {
        return response($company->faviconContents())
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
