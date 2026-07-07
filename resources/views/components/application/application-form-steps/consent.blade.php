@if ($consentSubStep === 1)
    <x-auth-header
        :title="__('Terms of Engagement')"
        :description="__('Please read the terms below in full before continuing.')"
    />

    <div class="mt-6 flex flex-col gap-6">
        <div class="flex max-h-112 flex-col gap-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            <p>{{ __(':company (“Employment Business”)', ['company' => $this->employmentBusinessName]) }}</p>

            <p>{{ __('Temporary Worker as detailed in the Assignment Schedule') }}</p>

            <p>{{ __('We are a member of the Recruitment & Employment Confederation (REC) and operate in line with its Code of Professional Practice.') }}</p>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('1. Definitions & Interpretation') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('References to the singular include the plural and vice versa.') }}</li>
                    <li>{{ __('Headings are for reference only and do not affect interpretation.') }}</li>
                    <li>{{ __('Definitions include terms such as "Agreement", "Assignment", "Client", "Employment Business", "Temporary Worker", and key legislation such as AWR 2010 and GDPR.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('2. The Contract') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('This is a contract for services, not employment. PAYE applies.') }}</li>
                    <li>{{ __('No contract exists between Assignments.') }}</li>
                    <li>{{ __('Variations must be in writing and signed.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('3. Pre-Assignment Information') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Details of the Assignment provided in writing.') }}</li>
                    <li>{{ __('After 12 weeks, Worker becomes entitled to AWR equal treatment rights.') }}</li>
                    <li>{{ __('Written statement of terms can be requested post-Qualifying Period.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('4. Agency Client Co-operation') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Client must assist with tracking the Qualifying Period and providing comparator info.') }}</li>
                    <li>{{ __('Client to report complaints or breaches related to AWR.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('5. Strike Cover') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Worker must not be supplied to cover official industrial action under Conduct Regulations.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('6. Worker Duties') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Follow Client\'s rules and health & safety policies.') }}</li>
                    <li>{{ __('Report relevant personal or legal issues promptly.') }}</li>
                    <li>{{ __('Maintain confidentiality and professionalism.') }}</li>
                    <li>{{ __('Provide qualifications and complete accurate timesheets by 9:00am Monday.') }}</li>
                    <li>{{ __('Declare prior engagements with the Client within the last 12 weeks.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('7. Working Time Regulations') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Accurate timesheets must be submitted weekly and signed by the Client.') }}</li>
                    <li>{{ __('Falsifying timesheets is a criminal offence.') }}</li>
                    <li>{{ __('Delays in timesheet submission may delay payment.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('8. Pay & Deductions') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Weekly pay via BACS with statutory deductions.') }}</li>
                    <li>{{ __('After the Qualifying Period, additional AWR entitlements apply.') }}</li>
                    <li>{{ __('Non-working days are unpaid unless agreed or statutory.') }}</li>
                    <li>{{ __('Agency may deduct overpayments with written notice.') }}</li>
                    <li>{{ __('Pension enrolment applies per AE regulations; opt-out is allowed.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('9. Holiday') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('5.6 weeks annual leave pro-rata.') }}</li>
                    <li>{{ __('Holiday year: 1 Jan – 31 Dec.') }}</li>
                    <li>{{ __('Public holidays count towards entitlement.') }}</li>
                    <li>{{ __('Holiday requests require written notice twice the length of leave requested.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('10. Assignment Termination') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Ends as scheduled or by notice in the Assignment Schedule.') }}</li>
                    <li>{{ __('Immediate termination possible for misconduct or force majeure.') }}</li>
                    <li>{{ __('Ends if the Client Agency agreement ends.') }}</li>
                    <li>{{ __('Lack of communication for 4 weeks leads to termination and issuance of P45.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('11. IP & Confidentiality') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('All IP created during the Assignment belongs to the Client.') }}</li>
                    <li>{{ __('Return of materials upon end of assignment is required.') }}</li>
                    <li>{{ __('Confidentiality extends 10 years after assignment ends.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('12. Data Protection') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Agency and Client are independent Data Controllers.') }}</li>
                    <li>{{ __('Worker consents to lawful data processing and transfer.') }}</li>
                    <li>{{ __('Compliance with data policies and breach reporting is required.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('13. Liability & Indemnity') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Worker indemnifies for breach of data/confidentiality/IP or false timesheets.') }}</li>
                    <li>{{ __('Improper termination results in liability for losses to Client/Agency.') }}</li>
                    <li>{{ __('Client responsible for on-site supervision and health & safety.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('14. Notice & Communication') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Communication methods include email, post, or in person.') }}</li>
                    <li>{{ __('Delivery deemed based on time and day of sending.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('15. General') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('Headings are non-binding.') }}</li>
                    <li>{{ __('Invalid terms are severable.') }}</li>
                    <li>{{ __('Assignment Schedule overrides conflicts.') }}</li>
                    <li>{{ __('No third-party rights except where specified.') }}</li>
                    <li>{{ __('Agency acts as employment business and agency where applicable.') }}</li>
                </ul>
            </div>

            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('16. Governing Law & Jurisdiction') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    <li>{{ __('This Agreement is governed by English law and the jurisdiction of the English courts.') }}</li>
                </ul>
            </div>
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
    <x-auth-header
        :title="__('Keeping Children Safe in Education')"
        :description="__('Please read the document below in full before continuing.')"
    />

    <div
        class="mt-6 flex flex-col gap-4"
        x-data="{
            scrolledToBottom: false,
            checkScroll() {
                const el = this.$refs.pdfScrollContainer;
                this.scrolledToBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 20;
            },
        }"
    >
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
    <x-auth-header
        :title="__('Declaration')"
        :description="__('Please read the declaration below in full before continuing.')"
    />

    <div class="mt-6 flex flex-col gap-6">
        <div class="flex max-h-112 flex-col gap-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            <p>{{ __(':company helps its clients and job seekers find employment. To be able to offer these services, we must handle personal data (including sensitive personal data), and in doing so, we take on the role of a data controller. This is the reason we are requesting your personal information on this form. We are required to follow all applicable data protection rules while handling your personal information. Due to these rules, we are required to provide you with a privacy statement outlining how we manage your personal data. You may find this statement on our website or upon request.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('To the best of my knowledge and comprehension, I will fill out every area of this application and will not missed any crucial information. I am aware that making any false representations might lead to the termination of my contract and put me in danger of facing legal action.') }}</p>

            <p>{{ __('I am aware that any information I provide on this form will be reviewed, and that my appointment to any post I may be given is contingent to successful completion of registration and qualification checks.') }}</p>

            <p>{{ __('I acknowledge that :company may use the information I will submit in this form and on any CV or other document to help me find employment, and that it may keep the information on file for as long as is reasonably required and in compliance with the Data Protection Act and all other applicable legislation.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('I accept that my personal information will be sent to customers of :company in order to provide job placement services. I give my approval to my personal information being saved electronically and on paper.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('I agree that, in addition to offering job search services, we may connect you to umbrella business providers that may use my personal information to process payroll.') }}</p>

            <p>{{ __('I am aware that :company may cross-reference the information I have provided with information kept by or provided to other parties, including utilising or providing information to third parties in order to prevent or detect crime, safeguard public money, or in any other manner allowed or required by law.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('I agree that :company may use the information about my criminal history to help me obtain employment and to comply with any legal requirements that may require such use.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('I agree that any necessary safeguarding and employment checks will be conducted in accordance with any applicable framework requirement, and that the appropriate authorities, its representatives, and any relevant professional body may access my personal information.') }}</p>

            <p>{{ __('I agree to references being sought.') }}</p>

            <p>{{ __('I certify that I have read part one of Keeping Children Safe in Education.') }}</p>

            <p>{{ __('I certify that I am qualified to work in the UK and accept that, if necessary, further checks will be made with the Home Office to confirm my eligibility.') }}</p>

            <p>{{ __('I certify that the information I will give you about the professional body will be accurate, and I permit any necessary checks to be made.') }}</p>

            <p>{{ __('I certify that I will tell :company right away if any of my personal information changes.', ['company' => config('app.name')]) }}</p>

            <p>{{ __('I hereby certify that I will not, without the prior written approval of the discloser, disclose any confidential information to any third party.') }}</p>
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
