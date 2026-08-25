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
 * Narrates a consultant's already-computed weekly performance figures into a
 * short briefing — never given tools or told to look anything up itself, so
 * it can't invent numbers beyond what's handed to it in the prompt.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Timeout(30)]
class PerformanceSummaryAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You write a short, direct performance briefing for a recruitment consultant, based only on the '.
            'figures given to you in the prompt — never invent or estimate a number that wasn\'t supplied. '.
            'Two to three sentences, plain prose, no headings or bullet points. Call out whichever figure stands '.
            'out most (particularly strong or particularly weak), and comment on the rebook rate trend if it\'s '.
            'given — a low rebook rate means next week is looking quiet compared to this week, a rebook rate at '.
            'or above 100% means next week is already as busy or busier. Write it like a quick note a manager '.
            'would actually read, not a generic summary.';
    }
}
