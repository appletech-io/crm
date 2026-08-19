<?php

namespace App\Livewire;

use App\Ai\Agents\DataAssistant;
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

    public function mount(): void
    {
        abort_unless(active_industry() !== null, 403);
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

        try {
            $response = (new DataAssistant)->prompt($prompt);

            $this->messages[] = ['role' => 'assistant', 'content' => $response->text];
        } catch (Throwable $e) {
            report($e);

            $this->messages[] = ['role' => 'assistant', 'content' => 'Sorry, something went wrong answering that.'];
        }

        $this->dispatch('message-added');
    }

    public function clearChat(): void
    {
        $this->messages = [];
    }

    public function render()
    {
        return view('livewire.ask-assistant');
    }
}
