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

@if ($consentSubStep === 4)
    <form wire:submit="saveSecurityClearance" class="flex flex-col gap-6">
        <div class="flex max-h-112 flex-col gap-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            <p>{{ __('The term "Disclosure" refers to both the document that is produced when a DBS check has been completed as well as the service offered by the Disclosure and Barring Scheme (DBS).') }}</p>

            <p>{{ __("The Rehabilitation of Offenders Act of 1974's exempted questions are met by :company, and all of our agency employees are submitted to Enhanced Disclosure checks from the Disclosure Barring Service. Details of any unfiltered warnings, reprimands, last warnings, and convictions will be included here.", ['company' => $this->employmentBusinessName]) }}</p>

            <p>{{ __(':company requires that all candidates must possess an enhanced child workforce DBS Certificate issued by :company or a DBS that is subscribed to the update service. We are unable to accept DBS certificates that have been processed for voluntary roles or certificates that have been checked against the Adult workforce due to the nature of the work :company offers. We fully adhere to the DBS Code of Practice, and :company organises the processing of DBS certifications. Every 6 months, the DBS status of the candidates DBS will be checked, this is done by checking the update service for any changes.', ['company' => $this->employmentBusinessName]) }}</p>

            <p>{{ __('The DBS cost is £64.20 and must be paid at the time of registration. Currently, we employ a Registered Umbrella Body called UCheck for the non-refundable process.') }}</p>

            <p>{{ __('The agency worker is responsible for the cost. The price for the Enhanced DBS is £49.50, the VAT is £2.45, and the administrative/processing costs are £12.25.') }}</p>

            <p>{{ __(':company can check for any changes once a year, the candidate must subscribe to the update service in order for this to happen. The candidate is in charge of paying the annual fee of £16 for the update service. Please visit :url for further information about the update service.', ['company' => $this->employmentBusinessName, 'url' => 'https://www.gov.uk/dbs-update-service']) }}</p>

            <p>{{ __("An application or candidate won't necessarily be rejected if they disclose prior offenses. Any issue raised in a disclosure will be discussed with the candidate.") }}</p>

            <p>{{ __('Visit :url for additional details on the DBS Code of Practice and the Disclosure and Barring Scheme.', ['url' => 'https://www.gov.uk/government/organisations/disclosure-and-barring-service/about']) }}</p>
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
        <div class="flex max-h-112 flex-col gap-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            <p>{{ __('You are applying for work in roles which are exempt from the Rehabilitation of Offenders Act 1974. For this reason, you are required to disclose information about both spent and unspent convictions.') }}</p>

            <p>{{ __('You are not required to declare any information about protected offences (offences to which the filtering rules apply). If you require further information about convictions which are unspent/spent, you can contact organisations such as :nacro or :unlock for further assistance.', ['nacro' => 'NACRO (https://www.nacro.org.uk)', 'unlock' => 'Unlock (http://www.unlock.org.uk)']) }}</p>

            <p>{{ __('We will seek to put forward/supply the best possible candidates to our clients. Having a criminal conviction will not necessarily exclude you from the process.') }}</p>

            <p>{{ __('Failure to declare a conviction may require us to exclude you from our register if the offence is not declared but later comes to light. If you are working in an assignment with a client at the time that we are made aware of a conviction that you have not disclosed to us, we may be legally required to inform our client of that information and your assignment may be terminated.') }}</p>
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
        <div class="flex flex-col gap-4 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-white/5 dark:text-zinc-400">
            <p>{{ __('Under the Working Time Regulations 1988 an individual is not permitted to work more than an average of 48 hours per week. By completing this application form you will be deemed to have opted out of these regulation. This means you will have the option to work for more than 48 hours per week if you wish. For clarification, you are not under any obligation to work more than 48 hours per week.') }}</p>
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
        <div class="flex max-h-112 flex-col gap-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            <p>{{ __("Please see the attached link to the Department of Education's Disqualification under the Childcare Act 2006 - Statutory Guidance for Schools (the statutory guidance), which is dated July 2018.") }}</p>

            <p><a href="https://www.gov.uk/government/publications/disqualification-under-the-childcare-act-2006" target="_blank" rel="noopener" class="text-[var(--color-accent)] underline">https://www.gov.uk/government/publications/disqualification-under-the-childcare-act-2006</a></p>

            <p>{{ __('It outlines the conditions under which people are prohibited from performing certain childcare job (related childcare labor) in accordance with the pertinent statutory laws. We must determine if any candidates seeking employment that would need relevant childcare work are barred from performing that kind of job as part of our safeguarding assessments. People may not be eligible if they have either been found guilty of or are under the control of a relevant order.') }}</p>

            <p>{{ __('If you can confirm the following, please consult the DfE Guidance, which offers more information, and indicate so below.') }}</p>

            <ul class="list-disc pl-5">
                <li>{{ __("The disclosure of one's own spent and unspent convictions is mandatory for specific jobs involving children and childcare.") }}</li>
                <li>{{ __('You are not, however, obligated to: provide any information on any protected (or filtered) offenses when completing this form.') }}</li>
                <li>{{ __("reveal any details on any third party's expired convictions.") }}</li>
            </ul>

            <p>{{ __('If you are ineligible under the applicable legislative restrictions, we must inform you that it is illegal for you to work in a relevant childcare function or to be directly involved with the administration of such a provider.') }}</p>

            <p>{{ __("We won't be able to hire you for a position that requires appropriate childcare duties if you are rejected. However, in accordance with the statutory guidelines, you may be able to apply to Ofsted for a waiver of disqualification. To learn more about the application procedure, you should get in touch with Ofsted directly.") }}</p>
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
