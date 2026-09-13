<x-filament-widgets::widget>
    <div class="flex flex-col gap-4">
        <div class="fi-sc-wizard">
            <ol role="list" class="fi-sc-wizard-header">
                <li @class(['fi-sc-wizard-header-step', 'fi-active' => $this->selectedStatusId === null])>
                    <button type="button" wire:click="selectStatus(null)" class="fi-sc-wizard-header-step-btn">
                        <div class="fi-sc-wizard-header-step-icon-ctn">
                            <span class="fi-sc-wizard-header-step-number">{{ $this->jobsCount() }}</span>
                        </div>

                        <div class="fi-sc-wizard-header-step-text">
                            <span class="fi-sc-wizard-header-step-label">Jobs</span>
                        </div>
                    </button>

                    <svg fill="none" preserveAspectRatio="none" viewBox="0 0 22 80" aria-hidden="true" class="fi-sc-wizard-header-step-separator">
                        <path d="M0 -2L20 40L0 82" stroke-linejoin="round" stroke="currentcolor" vector-effect="non-scaling-stroke"></path>
                    </svg>
                </li>

                <li @class(['fi-sc-wizard-header-step', 'fi-active' => $this->viewingCandidates])>
                    <button type="button" wire:click="selectCandidates" class="fi-sc-wizard-header-step-btn">
                        <div class="fi-sc-wizard-header-step-icon-ctn">
                            <span class="fi-sc-wizard-header-step-number">{{ $this->candidatesCount() }}</span>
                        </div>

                        <div class="fi-sc-wizard-header-step-text">
                            <span class="fi-sc-wizard-header-step-label">Candidates</span>
                        </div>
                    </button>

                    <svg fill="none" preserveAspectRatio="none" viewBox="0 0 22 80" aria-hidden="true" class="fi-sc-wizard-header-step-separator">
                        <path d="M0 -2L20 40L0 82" stroke-linejoin="round" stroke="currentcolor" vector-effect="non-scaling-stroke"></path>
                    </svg>
                </li>

                @foreach ($this->statuses() as $segment)
                    <li @class(['fi-sc-wizard-header-step', 'fi-active' => $this->selectedStatusId === $segment['status']->id])>
                        <button type="button" wire:click="selectStatus({{ $segment['status']->id }})" class="fi-sc-wizard-header-step-btn">
                            <div class="fi-sc-wizard-header-step-icon-ctn">
                                <span class="fi-sc-wizard-header-step-number">{{ $segment['open'] }}/{{ $segment['total'] }}</span>
                            </div>

                            <div class="fi-sc-wizard-header-step-text">
                                <span class="fi-sc-wizard-header-step-label">{{ $segment['status']->name }}</span>
                            </div>
                        </button>

                        @unless ($loop->last)
                            <svg fill="none" preserveAspectRatio="none" viewBox="0 0 22 80" aria-hidden="true" class="fi-sc-wizard-header-step-separator">
                                <path d="M0 -2L20 40L0 82" stroke-linejoin="round" stroke="currentcolor" vector-effect="non-scaling-stroke"></path>
                            </svg>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="flex justify-end">
            <div class="w-full max-w-56">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="poolId">
                        <option value="">All candidates</option>
                        @foreach ($this->poolOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="flex flex-col gap-2 rounded-lg border border-gray-200 p-2 dark:border-white/10">
            @if ($this->viewingCandidates)
                @forelse ($this->selectedCandidates() as $candidate)
                    <a
                        href="{{ \App\Filament\Resources\Candidates\CandidateResource::getUrl('edit', ['record' => $candidate]) }}"
                        class="flex items-center justify-between gap-4 rounded-md px-3 py-2 transition hover:bg-gray-100 dark:hover:bg-white/5"
                    >
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $candidate->first_name }} {{ $candidate->last_name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $candidate->jobTitle?->name ?? 'No job title' }}
                            </span>
                        </div>

                        @if ($candidate->current_status)
                            <x-filament::badge color="gray">
                                {{ $candidate->current_status }}
                            </x-filament::badge>
                        @endif
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No candidates here yet.</p>
                @endforelse

                @if ($this->candidatesCount() > count($this->selectedCandidates()))
                    <a href="{{ $this->candidatesUrl() }}" class="px-3 py-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        View all {{ $this->candidatesCount() }} candidates
                    </a>
                @endif
            @else
                @forelse ($this->selectedJobs() as $vacancy)
                    <a
                        href="{{ \App\Filament\Resources\Vacancies\VacancyResource::getUrl('edit', ['record' => $vacancy]) }}"
                        class="flex items-center justify-between gap-4 rounded-md px-3 py-2 transition hover:bg-gray-100 dark:hover:bg-white/5"
                    >
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $vacancy->title }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $vacancy->client?->name ?? 'General cover' }}
                                @if ($vacancy->consultant)
                                    · {{ $vacancy->consultant->name }}
                                @endif
                            </span>
                        </div>

                        <x-filament::badge :color="$vacancy->jobStatus?->color ?? 'gray'">
                            {{ $vacancy->jobStatus?->name ?? 'No status' }}
                        </x-filament::badge>
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No jobs here yet.</p>
                @endforelse

                @if ($this->selectedJobsCount() > count($this->selectedJobs()))
                    <a href="{{ $this->selectedJobsUrl() }}" class="px-3 py-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        View all {{ $this->selectedJobsCount() }} jobs
                    </a>
                @endif
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
