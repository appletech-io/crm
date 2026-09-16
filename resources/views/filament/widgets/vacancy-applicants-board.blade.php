@php
    $statuses = $this->statuses();
    $applicationsByStatus = $this->applicationsByStatus();
@endphp

<x-filament-widgets::widget>
    @if ($statuses->isEmpty())
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-gray-900">
            No Job Statuses have been configured for this industry yet — add some under Settings &rarr; Job Statuses to build this board.
        </div>
    @else
        <div class="mb-3 flex justify-end">
            <x-filament::button
                icon="heroicon-o-user-plus"
                size="sm"
                wire:click="mountAction('addCandidate')"
            >
                Add Candidate
            </x-filament::button>
        </div>

        <div
            x-data="{
                init() {
                    this.$el.querySelectorAll('[data-kanban-column]').forEach((column) => {
                        window.Sortable.create(column, {
                            group: 'vacancy-applicants-board-{{ $this->record->id }}',
                            animation: 150,
                            ghostClass: 'fi-sortable-ghost',
                            onEnd: (event) => {
                                const applicationId = event.item.dataset.applicationId;
                                const jobStatusId = event.to.dataset.statusId;

                                $wire.moveApplication(parseInt(applicationId), parseInt(jobStatusId));
                            },
                        });
                    });
                },
            }"
        >
            <div class="flex items-start gap-4 overflow-x-auto pb-2">
                @foreach ($statuses as $status)
                    @php $applications = $applicationsByStatus->get($status->id, collect()); @endphp

                    <div class="flex w-72 shrink-0 flex-col rounded-xl border border-gray-200 bg-gray-50/75 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2 dark:border-white/10">
                            <x-filament::badge :color="$status->color">
                                {{ $status->name }}
                            </x-filament::badge>

                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $applications->count() }}
                            </span>
                        </div>

                        <div
                            data-kanban-column
                            data-status-id="{{ $status->id }}"
                            class="flex min-h-24 flex-1 flex-col gap-2 p-2"
                        >
                            @foreach ($applications as $application)
                                <div
                                    wire:key="application-{{ $application->id }}"
                                    data-application-id="{{ $application->id }}"
                                    class="fi-sortable-item cursor-move rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-800"
                                >
                                    @php $candidateName = trim("{$application->candidate?->first_name} {$application->candidate?->last_name}") ?: '—'; @endphp

                                    <div class="flex items-start justify-between gap-2">
                                        @if ($url = $this->candidateProfileUrl($application))
                                            <a
                                                href="{{ $url }}"
                                                target="_blank"
                                                class="text-sm font-medium text-gray-950 hover:underline dark:text-white"
                                            >
                                                {{ $candidateName }}
                                            </a>
                                        @else
                                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $candidateName }}</span>
                                        @endif

                                        @if ($application->match_strength !== null)
                                            <x-filament::badge :color="match (true) {
                                                $application->match_strength >= 70 => 'success',
                                                $application->match_strength >= 40 => 'warning',
                                                default => 'danger',
                                            }">
                                                {{ $application->match_strength }}%
                                            </x-filament::badge>
                                        @endif
                                    </div>

                                    @if ($application->candidate?->email)
                                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $application->candidate->email }}
                                        </p>
                                    @endif

                                    @if ($this->canSendApplicationForm($application) || $this->canCreateBooking($application) || $this->canMarkPlaced($application) || $this->canEditSalary($application))
                                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                            @if ($this->canSendApplicationForm($application))
                                                <button
                                                    type="button"
                                                    wire:click="mountAction('sendApplicationForm', { applicationId: {{ $application->id }} })"
                                                    class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-300"
                                                >
                                                    Send Application Form
                                                </button>
                                            @endif

                                            @if ($this->canCreateBooking($application))
                                                <a
                                                    href="{{ $this->createBookingUrl($application) }}"
                                                    class="text-xs font-medium text-success-600 hover:underline dark:text-success-400"
                                                >
                                                    Create Booking
                                                </a>
                                            @endif

                                            @if ($this->canMarkPlaced($application))
                                                <button
                                                    type="button"
                                                    wire:click="mountAction('markPlaced', { applicationId: {{ $application->id }} })"
                                                    class="text-xs font-medium text-success-600 hover:underline dark:text-success-400"
                                                >
                                                    Mark as Placed
                                                </button>
                                            @endif

                                            @if ($this->canEditSalary($application))
                                                <button
                                                    type="button"
                                                    wire:click="mountAction('editSalary', { applicationId: {{ $application->id }} })"
                                                    class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-300"
                                                >
                                                    Edit Salary
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-widgets::widget>
