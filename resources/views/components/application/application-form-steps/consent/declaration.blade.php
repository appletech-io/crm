{{--
    The Declaration text a candidate reads and agrees to during the
    application form's consent step. Shared with the read-only "View
    Application" page so staff see exactly what the candidate agreed to.
--}}
<div class="flex flex-col gap-4">
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
