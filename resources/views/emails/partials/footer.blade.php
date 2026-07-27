<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;border-top:1px solid #e0e0e0;padding-top:16px;font-family:Arial,Helvetica,sans-serif;">
    <tr>
        <td style="padding-right:16px;vertical-align:top;">
            <img src="cid:{{ $logoContentId }}" alt="{{ $company->name }}" width="120" style="display:block;width:120px;height:auto;">
        </td>
        <td style="vertical-align:top;font-size:13px;color:#333333;line-height:1.5;">
            @if ($consultant)
                <strong style="font-size:14px;color:#111111;">{{ $consultant->name }}</strong><br>
                {{ $consultant->job_title ?: 'Consultant' }}<br>
                <br>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    @if ($companyPhone)
                        <tr>
                            <td style="padding-right:6px;color:#666666;">t:</td>
                            <td>{{ $companyPhone }}</td>
                        </tr>
                    @endif
                    @if ($consultant->mobile)
                        <tr>
                            <td style="padding-right:6px;color:#666666;">m:</td>
                            <td>{{ $consultant->mobile }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding-right:6px;color:#666666;">e:</td>
                        <td><a href="mailto:{{ $consultant->email }}" style="color:#333333;">{{ $consultant->email }}</a></td>
                    </tr>
                    @if ($companyWebsite)
                        <tr>
                            <td style="padding-right:6px;color:#666666;">w:</td>
                            <td><a href="{{ $companyWebsite }}" style="color:#333333;">{{ $companyWebsiteLabel }}</a></td>
                        </tr>
                    @endif
                </table>
            @else
                <strong style="font-size:14px;color:#111111;">{{ $company->name }}</strong><br>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    @if ($companyPhone)
                        <tr>
                            <td style="padding-right:6px;color:#666666;">t:</td>
                            <td>{{ $companyPhone }}</td>
                        </tr>
                    @endif
                    @if ($companyWebsite)
                        <tr>
                            <td style="padding-right:6px;color:#666666;">w:</td>
                            <td><a href="{{ $companyWebsite }}" style="color:#333333;">{{ $companyWebsiteLabel }}</a></td>
                        </tr>
                    @endif
                </table>
            @endif
        </td>
    </tr>
</table>
