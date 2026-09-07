<?php

namespace App\Livewire;

use App\Ai\Agents\DataAssistant;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.chat')]
#[Title('Ask Assistant')]
class AskAssistant extends Component
{
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $prompt = '';

    /**
     * The agent remembers conversation history itself (see DataAssistant's
     * Conversational + RemembersConversations) — this just tracks which
     * conversation to continue across Livewire requests.
     */
    public ?string $conversationId = null;

    public bool $moreResultsAvailable = false;

    /**
     * The prompt to send to the agent once respond() runs. Split from
     * send()/showMore() into its own request so the user's own message
     * renders immediately, instead of waiting alongside the (much slower)
     * agent reply for a single response to come back.
     */
    public ?string $pendingPrompt = null;

    /**
     * True when embedded as the small floating popup rather than the full
     * /crm/ask-assistant page — shows an "expand" link to that page instead
     * of relying on the layout around it, since the popup has none.
     */
    public bool $isPopup = false;

    public function mount(bool $isPopup = false): void
    {
        abort_unless(active_industry() !== null, 403);

        $this->isPopup = $isPopup;
    }

    /** @return array<int, string> */
    public function suggestedPrompts(): array
    {
        $qualification = $this->exampleQualification();

        $qualificationPrompt = $qualification
            ? "Which candidates have {$qualification} qualification and are Live?"
            : 'Which candidates are Live?';

        return [
            'Show me bookings for a client this month',
            $qualificationPrompt,
            'Find vacancies in Leicester',
            "How many bookings do I have this week and what's my margin?",
        ];
    }

    /**
     * Grouped, more detailed prompt examples shown in the "help" modal —
     * covers every tool, not just the four headline suggestions.
     *
     * @return array<string, array<int, string>>
     */
    public function promptExamples(): array
    {
        $qualification = $this->exampleQualification();

        $qualificationPrompt = $qualification
            ? "Which candidates have {$qualification} qualification and are Live?"
            : 'Which candidates are Live?';

        return [
            'Bookings' => [
                'Show me bookings for a client this month',
                'What bookings do I have in Leicester right now?',
                'Find completed bookings for a candidate by name',
            ],
            'Clients' => [
                "Find clients matching 'Primary'",
                'Which clients are in Manchester?',
                'Show me all Secondary School clients',
            ],
            'Candidates' => [
                $qualificationPrompt,
                'Find candidates with a specific skill in Leicester',
            ],
            'Vacancies' => [
                'Find open vacancies in Leicester',
                'Show me vacancies for a specific client',
            ],
            'Your performance' => [
                "How many bookings do I have this week and what's my margin?",
                "What's a named consultant's performance this week? (admins only)",
            ],
            'Vacancy matches' => [
                'Who matches a specific vacancy?',
                'Which vacancies suit a specific candidate?',
            ],
            'Can this candidate be booked?' => [
                'Can a candidate be booked as a specific job title?',
                'Is a candidate available next Monday?',
            ],
            'Compliance' => [
                "Which candidates have {$this->exampleComplianceRequirement()} expiring soon?",
            ],
            'Nearby candidates' => [
                'Which candidates are within 10 miles of a specific client?',
                'Find candidates near a postcode or address',
            ],
            'Best-rated candidates nearby' => [
                "Find me a good {$this->exampleSkillOrQualification()} near a client",
            ],
            'Draft an email' => [
                'Write a follow-up email to a candidate about their application',
                'Draft a booking confirmation email for a client',
            ],
        ];
    }

    private function exampleQualification(): ?string
    {
        return match (active_industry()) {
            'education' => 'a Teacher',
            'healthcare' => 'a Nursing',
            default => null,
        };
    }

    private function exampleSkillOrQualification(): string
    {
        return match (active_industry()) {
            'education' => 'maths teacher',
            'healthcare' => 'nurse',
            default => 'candidate',
        };
    }

    private function exampleComplianceRequirement(): string
    {
        return match (active_industry()) {
            'education' => 'Safeguarding Training',
            default => 'DBS',
        };
    }

    public function useSuggestion(string $suggestion): void
    {
        $this->prompt = $suggestion;
    }

    public function send(): void
    {
        $prompt = trim($this->prompt);

        if ($prompt === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $prompt];
        $this->prompt = '';
        $this->pendingPrompt = $prompt;

        $this->dispatch('message-added');
    }

    /**
     * Asks for the next page of the last search — the agent can work out the
     * right offset itself from the conversation history it already has.
     */
    public function showMore(): void
    {
        $this->messages[] = ['role' => 'user', 'content' => 'Show me more'];
        $this->pendingPrompt = 'Show me more of the results from my last search.';

        $this->dispatch('message-added');
    }

    /**
     * Runs the (slow) agent call for whatever send()/showMore() just queued.
     * Kept as its own request so the user's message from send()/showMore()
     * has already rendered before this one starts.
     */
    public function respond(): void
    {
        if ($this->pendingPrompt === null) {
            return;
        }

        $prompt = $this->pendingPrompt;
        $this->pendingPrompt = null;

        $this->askAssistant($prompt);
    }

    private function askAssistant(string $prompt): void
    {
        try {
            $agent = new DataAssistant;

            $agent = $this->conversationId
                ? $agent->continue($this->conversationId, auth()->user())
                : $agent->forUser(auth()->user());

            $response = $agent->prompt($prompt);
            $text = $response->text;

            $this->conversationId = $response->conversationId;
            $this->moreResultsAvailable = Str::contains($text, 'more match');

            $this->messages[] = ['role' => 'assistant', 'content' => $text];
        } catch (Throwable $e) {
            report($e);

            $this->moreResultsAvailable = false;
            $this->messages[] = ['role' => 'assistant', 'content' => 'Sorry, something went wrong answering that.'];
        }

        $this->dispatch('message-added');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->conversationId = null;
        $this->moreResultsAvailable = false;
        $this->pendingPrompt = null;
    }

    public function render()
    {
        return view('livewire.ask-assistant');
    }
}
