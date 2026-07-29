{{--
    The Childcare Act 2006 disqualification explainer text shown to a
    candidate during the application form's consent step, before they answer
    the DfE Guidance questions. Shared with the read-only "View Application"
    page so staff see exactly what the candidate was told.
--}}
<div class="flex flex-col gap-4">
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
