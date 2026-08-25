<?php

namespace App\Http\Controllers;

use App\Http\Resources\VacancyFeedResource;
use App\Models\Vacancy;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VacancyFeedController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $vacancies = Vacancy::query()
            ->publiclyListed()
            ->with(['consultant', 'jobTitle', 'client'])
            ->orderByDesc('created_at')
            ->get();

        return VacancyFeedResource::collection($vacancies);
    }
}
