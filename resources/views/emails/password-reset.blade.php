<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;line-height:1.6;">
    <tr>
        <td>
            <p style="margin:0 0 16px;">Hi {{ $user->name }},</p>

            <p style="margin:0 0 16px;">
                We received a request to reset the password for your {{ $company->name }} account
                ({{ $user->email }}). Click the button below to choose a new one.
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $resetUrl }}" style="display:inline-block;background:#111111;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:4px;font-weight:bold;">Reset password</a>
            </p>

            <p style="margin:0 0 16px;">
                This link expires in {{ $expiresInMinutes }} minutes. If the button doesn't work, copy and paste this
                address into your browser:<br>
                <a href="{{ $resetUrl }}" style="color:#333333;word-break:break-all;">{{ $resetUrl }}</a>
            </p>

            <p style="margin:0;">
                If you didn't request a password reset, you can safely ignore this email — your password won't change.
            </p>
        </td>
    </tr>
</table>
