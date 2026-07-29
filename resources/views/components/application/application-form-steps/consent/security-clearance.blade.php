{{--
    The Security Clearance (DBS) explainer text shown to a candidate during
    the application form's consent step, before they answer the agree /
    overseas-residency questions. Shared with the read-only "View
    Application" page so staff see exactly what the candidate was told.

    @param string $companyName
--}}
<div class="flex flex-col gap-4">
    <p>{{ __('The term "Disclosure" refers to both the document that is produced when a DBS check has been completed as well as the service offered by the Disclosure and Barring Scheme (DBS).') }}</p>

    <p>{{ __("The Rehabilitation of Offenders Act of 1974's exempted questions are met by :company, and all of our agency employees are submitted to Enhanced Disclosure checks from the Disclosure Barring Service. Details of any unfiltered warnings, reprimands, last warnings, and convictions will be included here.", ['company' => $companyName]) }}</p>

    <p>{{ __(':company requires that all candidates must possess an enhanced child workforce DBS Certificate issued by :company or a DBS that is subscribed to the update service. We are unable to accept DBS certificates that have been processed for voluntary roles or certificates that have been checked against the Adult workforce due to the nature of the work :company offers. We fully adhere to the DBS Code of Practice, and :company organises the processing of DBS certifications. Every 6 months, the DBS status of the candidates DBS will be checked, this is done by checking the update service for any changes.', ['company' => $companyName]) }}</p>

    <p>{{ __('The DBS cost is £64.20 and must be paid at the time of registration. Currently, we employ a Registered Umbrella Body called UCheck for the non-refundable process.') }}</p>

    <p>{{ __('The agency worker is responsible for the cost. The price for the Enhanced DBS is £49.50, the VAT is £2.45, and the administrative/processing costs are £12.25.') }}</p>

    <p>{{ __(':company can check for any changes once a year, the candidate must subscribe to the update service in order for this to happen. The candidate is in charge of paying the annual fee of £16 for the update service. Please visit :url for further information about the update service.', ['company' => $companyName, 'url' => 'https://www.gov.uk/dbs-update-service']) }}</p>

    <p>{{ __("An application or candidate won't necessarily be rejected if they disclose prior offenses. Any issue raised in a disclosure will be discussed with the candidate.") }}</p>

    <p>{{ __('Visit :url for additional details on the DBS Code of Practice and the Disclosure and Barring Scheme.', ['url' => 'https://www.gov.uk/government/organisations/disclosure-and-barring-service/about']) }}</p>
</div>
