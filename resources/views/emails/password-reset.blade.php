<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f0f2f7; padding:32px 0; margin:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(90deg,#1A0A0A,#7A0019); padding:24px 32px;">
                            <span style="color:#fff; font-weight:900; font-size:16px;">RichWorks KPI Dashboard</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:15px; color:#1e293b; margin:0 0 16px;">Hi {{ $name }},</p>
                            <p style="font-size:14px; color:#475569; line-height:1.6; margin:0 0 24px;">
                                We received a request to reset your KPI Dashboard password. Click the button below to choose a new one — this link expires in 30 minutes.
                            </p>
                            <p style="text-align:center; margin:0 0 24px;">
                                <a href="{{ $resetUrl }}" style="display:inline-block; background:linear-gradient(135deg,#2d5548,#4a7c6b); color:#fff; text-decoration:none; font-weight:700; font-size:14px; padding:12px 28px; border-radius:12px;">
                                    Reset Password
                                </a>
                            </p>
                            <p style="font-size:12px; color:#94a3b8; line-height:1.6; margin:0;">
                                If you didn't request this, you can safely ignore this email — your password won't change.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
