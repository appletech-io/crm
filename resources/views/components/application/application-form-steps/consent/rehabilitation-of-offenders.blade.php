{{--
    The Rehabilitation of Offenders explainer text shown to a candidate
    during the application form's consent step, before they disclose
    spent/unspent convictions. Shared with the read-only "View Application"
    page so staff see exactly what the candidate was told.
--}}
<div class="flex flex-col gap-4">
    <p>{{ __('You are applying for work in roles which are exempt from the Rehabilitation of Offenders Act 1974. For this reason, you are required to disclose information about both spent and unspent convictions.') }}</p>

    <p>{{ __('You are not required to declare any information about protected offences (offences to which the filtering rules apply). If you require further information about convictions which are unspent/spent, you can contact organisations such as :nacro or :unlock for further assistance.', ['nacro' => 'NACRO (https://www.nacro.org.uk)', 'unlock' => 'Unlock (http://www.unlock.org.uk)']) }}</p>

    <p>{{ __('We will seek to put forward/supply the best possible candidates to our clients. Having a criminal conviction will not necessarily exclude you from the process.') }}</p>

    <p>{{ __('Failure to declare a conviction may require us to exclude you from our register if the offence is not declared but later comes to light. If you are working in an assignment with a client at the time that we are made aware of a conviction that you have not disclosed to us, we may be legally required to inform our client of that information and your assignment may be terminated.') }}</p>
</div>
