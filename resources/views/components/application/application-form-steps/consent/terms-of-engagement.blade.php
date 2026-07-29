{{--
    The Terms of Engagement text a candidate reads and agrees to during the
    application form's consent step. Shared with the read-only "View
    Application" page so staff see exactly what the candidate agreed to.

    @param string $companyName
--}}
<div class="flex flex-col gap-4">
    <p>{{ __(':company (“Employment Business”)', ['company' => $companyName]) }}</p>

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
