<div class="mb-6 flex flex-col gap-3">
    <div class="flex items-center justify-end text-sm">
        <span class="text-zinc-500 dark:text-zinc-400">
            {{ __('Section :current of :total', ['current' => $consentSubStep, 'total' => $this->totalConsentSubSteps]) }} &middot; {{ $this->consentSubStepProgressPercentage }}%
        </span>
    </div>

    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="h-full rounded-full bg-[var(--color-accent)] transition-all duration-300" style="width: {{ $this->consentSubStepProgressPercentage }}%"></div>
    </div>

    <div class="flex items-center justify-between">
        <flux:button
            type="button"
            icon="chevron-left"
            square
            size="sm"
            variant="ghost"
            aria-label="{{ __('Back') }}"
            wire:click="viewConsentSubStep({{ $consentSubStep - 1 }})"
            :disabled="$consentSubStep <= 1"
        />

        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ $this->consentSubStepLabels[$consentSubStep] ?? '' }}
        </span>

        <flux:button
            type="button"
            icon="chevron-right"
            square
            size="sm"
            variant="ghost"
            aria-label="{{ __('Forward') }}"
            wire:click="viewConsentSubStep({{ $consentSubStep + 1 }})"
            :disabled="$consentSubStep >= $this->furthestConsentSubStep"
        />
    </div>
</div>

@if ($consentSubStep === 1)
    <div class="flex flex-col gap-6">
        <div class="max-h-112 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.terms-of-engagement', ['companyName' => $this->employmentBusinessName])
        </div>

        <flux:checkbox
            wire:model="terms_of_engagement_accepted"
            :label="__('I have read, understood, and agree to the Terms of Engagement above')"
        />

        @error('terms_of_engagement_accepted')
            <flux:error>{{ $message }}</flux:error>
        @enderror

        <flux:button
            type="button"
            variant="primary"
            class="w-full"
            wire:click="acceptTermsOfEngagement"
            x-bind:disabled="!$wire.terms_of_engagement_accepted"
        >
            {{ __('Next') }}
        </flux:button>
    </div>
@endif

@if ($consentSubStep === 2)
    <div
        class="flex flex-col gap-4"
        x-data="{
            scrolledToBottom: false,
            checkScroll() {
                const el = this.$refs.pdfScrollContainer;
                this.scrolledToBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 20;
            },
        }"
    >
        <p x-show="!scrolledToBottom" class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Please scroll to the bottom of the document before confirming.') }}
        </p>

        <div
            x-ref="pdfScrollContainer"
            x-on:scroll="checkScroll()"
            x-init="$nextTick(() => checkScroll())"
            class="h-[80vh] overflow-y-auto rounded-lg border border-zinc-200 dark:border-white/10"
        >
            <embed
                src="{{ $this->kcsiePdfUrl }}"
                type="application/pdf"
                class="h-[160vh] w-full"
            />
        </div>

        <flux:checkbox
            wire:model="terms_accepted"
            x-bind:disabled="!scrolledToBottom"
            :label="__('I confirm that I have read and understood the document above')"
        />

        @error('terms_accepted')
            <flux:error>{{ $message }}</flux:error>
        @enderror

        <flux:button
            type="button"
            variant="primary"
            class="w-full"
            wire:click="acceptTerms"
            x-bind:disabled="!$wire.terms_accepted"
        >
            {{ __('Next') }}
        </flux:button>
    </div>
@endif

@if ($consentSubStep === 3)
    <div class="flex flex-col gap-6">
        <div class="max-h-112 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.declaration')
        </div>

        <flux:checkbox
            wire:model="declaration_accepted"
            :label="__('I have read, understood, and agree to the declaration above')"
        />

        @error('declaration_accepted')
            <flux:error>{{ $message }}</flux:error>
        @enderror

        <flux:button
            type="button"
            variant="primary"
            class="w-full"
            wire:click="acceptDeclaration"
            x-bind:disabled="!$wire.declaration_accepted"
        >
            {{ __('Next') }}
        </flux:button>
    </div>
@endif

@if ($consentSubStep === 4)
    <form wire:submit="saveSecurityClearance" class="flex flex-col gap-6">
        <div class="max-h-112 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.security-clearance', ['companyName' => $this->employmentBusinessName])
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="security_clearance_agreed"
                variant="segmented"
                :label="__('Do you agree?')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('security_clearance_agreed')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="lived_overseas_six_months"
                variant="segmented"
                :label="__('Have you been overseas in one country for an uninterrupted period of 6 months or more within the last 5 years?')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('lived_overseas_six_months')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div x-show="$wire.lived_overseas_six_months === 'yes'">
            <flux:textarea
                wire:model="overseas_details"
                :label="__('Please specify any applicable nations')"
                :description="__(':company may need a police clearance from any country that meets the requirements listed above, so please specify any applicable nations: if you have an overseas police check and it was finished before you left the country in question, it should not have been given more than six months before your departure date.', ['company' => $this->employmentBusinessName])"
                rows="4"
            />

            @error('overseas_details')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Next') }}
        </flux:button>
    </form>
@endif

@if ($consentSubStep === 5)
    <form wire:submit="saveRehabilitationOfOffenders" class="flex flex-col gap-6">
        <div class="max-h-112 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.rehabilitation-of-offenders')
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="unspent_convictions"
                variant="segmented"
                :label="__('Do you have any unspent conditional cautions or convictions under the Rehabilitation of Offenders Act 1974?')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('unspent_convictions')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div x-show="$wire.unspent_convictions === 'yes'">
            <flux:textarea
                wire:model="unspent_convictions_details"
                :label="__('Additional information (optional)')"
                :description="__('If you have declared any convictions you are welcome to provide us with any additional information that you think may be relevant and which will help us to determine your suitability to be put forward for roles with our clients. This could include, for example information about the circumstances of the offence, any work (paid or voluntary) or training that you have undertaken since, change in your circumstances etc.')"
                rows="4"
            />

            @error('unspent_convictions_details')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="spent_convictions_not_protected"
                variant="segmented"
                :label="__('Do you have any adult cautions (simple or conditional) or spent convictions that are not protected as defined by the Rehabilitation of Offenders Act 1974 (Exceptions) Order 1975 (Amendment) (England and Wales) Order 2020?')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('spent_convictions_not_protected')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Next') }}
        </flux:button>
    </form>
@endif

@if ($consentSubStep === 6)
    <form wire:submit="saveWorkingTimeRegulations" class="flex flex-col gap-6">
        <div class="rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-white/5 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.working-time-regulations')
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="working_time_regulations_opt_out"
                variant="segmented"
                :label="__('Do you agree to opt out of the Working Time Regulations 1988 48-hour weekly limit?')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('working_time_regulations_opt_out')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Next') }}
        </flux:button>
    </form>
@endif

@if ($consentSubStep === 7)
    <form wire:submit="saveDisqualificationUnderChildcareAct" class="flex flex-col gap-6">
        <div class="max-h-112 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            @include('components.application.application-form-steps.consent.childcare-act-disqualification')
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="childcare_act_guidance_read"
                variant="segmented"
                :label="__('I accept that I have read the DfE Guidance.')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('childcare_act_guidance_read')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div x-show="$wire.childcare_act_guidance_read === 'no'">
            <flux:textarea
                wire:model="childcare_act_guidance_read_details"
                :label="__('If you are unable to accept the above, please provide further details below')"
                rows="4"
            />

            @error('childcare_act_guidance_read_details')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="childcare_act_no_disqualification_reasons"
                variant="segmented"
                :label="__('I acknowledge that none of the reasons listed in the DfE Guidance entitle me to a disqualification.')"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('childcare_act_no_disqualification_reasons')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div x-show="$wire.childcare_act_no_disqualification_reasons === 'no'">
            <flux:textarea
                wire:model="childcare_act_no_disqualification_reasons_details"
                :label="__('If you are unable to acknowledge the above statement, please provide further details below')"
                rows="4"
            />

            @error('childcare_act_no_disqualification_reasons_details')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <flux:radio.group
                wire:model="childcare_act_will_notify_changes"
                variant="segmented"
                :label="__('I certify that if any of the aforementioned changes, I will tell :company right away.', ['company' => $this->employmentBusinessName])"
            >
                <flux:radio value="yes" label="{{ __('Yes') }}" />
                <flux:radio value="no" label="{{ __('No') }}" />
            </flux:radio.group>

            @error('childcare_act_will_notify_changes')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div x-show="$wire.childcare_act_will_notify_changes === 'no'">
            <flux:textarea
                wire:model="childcare_act_will_notify_changes_details"
                :label="__('If you are unable to confirm the above, please provide further details below')"
                rows="4"
            />

            @error('childcare_act_will_notify_changes_details')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Next') }}
        </flux:button>
    </form>
@endif
