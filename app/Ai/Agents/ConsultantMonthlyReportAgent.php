<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Narrates a consultant's already-computed period figures and activity
 * counts into a longer report than {@see PerformanceSummaryAgent} — never
 * given tools or told to look anything up itself, so it can't invent
 * numbers beyond what's handed to it in the prompt.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Timeout(45)]
class ConsultantMonthlyReportAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You write a detailed performance report for a recruitment consultant, covering a period of one or '.
            'several months, based only on the figures given to you in the prompt — never invent or estimate a '.
            'number that wasn\'t supplied. Three short paragraphs, plain prose, no headings or bullet points: '.
            '(1) the headline financial and placement figures for the period, calling out whichever stands out '.
            'most; (2) the week-by-week trend if it\'s given — is the consultant\'s workload growing, shrinking, or '.
            'steady, and how does the rebook rate look; (3) their activity levels (calls, meetings, notes) over the '.
            'period and what that suggests about their pipeline effort relative to their output. Write it like a '.
            'report a manager would actually read before a 1:1, not a generic summary.';
    }
}
