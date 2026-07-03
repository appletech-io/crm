<div class="flex flex-col gap-6">

    @include('components.application.application-form-steps.progress')

    {{-- AI parse error --}}
    @if ($parseError)
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $parseError }}
        </div>
    @endif

    {{-- Loading state while CV is being parsed --}}
    <div wire:loading wire:target="parseCv" class="py-12 text-center">
        <flux:icon.loading class="mx-auto block size-10 text-zinc-500" />
        <div class="mt-12">
            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Analysing your CV&hellip;</p>
            <p class="mt-1 text-xs text-zinc-500">This usually takes around 30 seconds.</p>
        </div>
    </div>

    {{-- Step 1: CV upload --}}
    @if ($currentStep === 1)
        @include('components.application.application-form-steps.cv-upload')
    @endif

    {{-- Step 2: Personal details --}}
    @if ($currentStep === 2)
        @include('components.application.application-form-steps.personal-details')
    @endif

    {{-- Step 3: Photo upload --}}
    @if ($currentStep === 3)
        @include('components.application.application-form-steps.photo-upload')
    @endif

    {{-- Step 4: Skills & work preferences --}}
    @if ($currentStep === 4)
        @include('components.application.application-form-steps.skills-work')
    @endif

    {{-- Step 5: Employment history --}}
    @if ($currentStep === 5)
        @include('components.application.application-form-steps.employment-history')
    @endif

    {{-- Step 6: References --}}
    @if ($currentStep === 6)
        @include('components.application.application-form-steps.references')
    @endif

</div>