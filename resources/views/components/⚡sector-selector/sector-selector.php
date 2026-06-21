<?php

use App\Models\Industry;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public ?string $industry_slug = null;

    public array $industries = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->industries = $user->industries()
            ->get()
            ->mapWithKeys(fn (Industry $industry) => [
                $industry->slug => $industry->name,
            ])
            ->toArray();

        $this->industry_slug = array_key_first($this->industries);
    }

    public function save(): void
    {
        $this->validate([
            'industry_slug' => ['required', 'string', 'in:'.implode(',', array_keys($this->industries))],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $industry = Industry::where('slug', $this->industry_slug)->firstOrFail();

        Cache::put("user.{$user->id}.active_industry", $industry->slug, now()->addHour());
        Cache::put("user.{$user->id}.active_industry_id", $industry->id, now()->addHour());

        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.auth');
    }
};
