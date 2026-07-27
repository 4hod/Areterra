<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Inter',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center" style="padding:24px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#00345C;padding:20px 28px;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $orgName }}" style="height:32px;display:block;">
                        @else
                            <span style="color:#ffffff;font-size:20px;font-weight:800;">{{ $orgName }}</span>
                        @endif
                        <span style="color:#009DE6;font-size:12px;display:block;margin-top:6px;">Animals. People. Purpose.</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;color:#0f172a;font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $bodyText }}</td>
                </tr>
                <tr>
                    <td style="padding:20px 28px;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px;line-height:1.5;">
                        Areterra &middot; Little Croft, Fenn Green, WV15 6JA &middot; 01562 307 306<br>
                        team@areterra.co.uk &middot; Registered charity No. 1196211
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
