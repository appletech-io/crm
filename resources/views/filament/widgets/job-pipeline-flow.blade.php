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
                                <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $segment['applicants'] }} {{ \Illuminate\Support\Str::plural('candidate', $segment['applicants']) }}</span>
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
                @php $candidates = $this->selectedCandidates(); @endphp

                @forelse ($candidates as $candidate)
                    <a
                        href="{{ $this->candidateEditUrl($candidate) }}"
                        class="flex items-center justify-between gap-4 rounded-md px-3 py-2 transition hover:bg-gray-100 dark:hover:bg-white/5"
                    >
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $candidate->first_name }} {{ $candidate->last_name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $candidate->jobTitle?->name ?? 'No job title' }}
                                @if ($candidate->consultant)
                                    · {{ $candidate->consultant->name }}
                                @endif
                                @if ($candidate->average_rating !== null)
                                    · ★ {{ number_format($candidate->average_rating, 1) }} ({{ $candidate->ratings_count }})
                                @endif
                            </span>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <x-filament::badge :color="$candidate->compliance_completed_at ? 'success' : 'gray'">
                                {{ $candidate->compliance_completed_at ? 'Compliant' : 'Incomplete' }}
                            </x-filament::badge>

                            @if ($candidate->current_status)
                                <x-filament::badge color="gray">
                                    {{ $candidate->current_status }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No candidates here yet.</p>
                @endforelse

                @if ($this->candidatesCount() > $candidates->count())
                    <div
                        wire:key="candidates-sentinel-{{ $this->candidatesLimit }}"
                        x-intersect.once="$wire.loadMoreCandidates()"
                        class="px-3 py-2 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        Loading more…
                    </div>
                @endif
            @else
                @php $jobs = $this->selectedJobs(); @endphp

                @forelse ($jobs as $vacancy)
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
                                · {{ $vacancy->employment_type?->label() ?? 'Unknown type' }}
                                · {{ $vacancy->placements_count }}/{{ $vacancy->positions_available }} filled
                            </span>
                        </div>

                        <x-filament::badge :color="$vacancy->jobStatus?->color ?? 'gray'">
                            {{ $vacancy->jobStatus?->name ?? 'No status' }}
                        </x-filament::badge>
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No jobs here yet.</p>
                @endforelse

                @if ($this->selectedJobsCount() > $jobs->count())
                    <div
                        wire:key="jobs-sentinel-{{ $this->jobsLimit }}"
                        x-intersect.once="$wire.loadMoreJobs()"
                        class="px-3 py-2 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        Loading more…
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
