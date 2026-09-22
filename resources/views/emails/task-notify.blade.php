<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f0f2f7; padding:32px 0; margin:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(90deg,#1A0A0A,#7A0019); padding:24px 32px;">
                            <span style="color:#fff; font-weight:900; font-size:16px;">RichWorks Performix</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:15px; color:#1e293b; margin:0 0 16px;">Hi,</p>
                            <p style="font-size:15px; color:#1e293b; margin:0 0 12px;">{{ $creatorName }} added a task and wanted to keep you in the loop:</p>
                            <p style="font-size:16px; font-weight:700; color:#1e293b; margin:0 0 8px;">{{ $taskTitle }}</p>
                            @if($taskDescription)
                                <p style="font-size:14px; color:#475569; line-height:1.6; margin:0 0 16px;">{{ $taskDescription }}</p>
                            @endif
                            @if($dueDate)
                                <p style="font-size:13px; color:#475569; margin:0 0 24px;">
                                    Due {{ \Illuminate\Support\Carbon::parse($dueDate)->format('j M Y') }}{{ $dueTime ? ' at ' . \Illuminate\Support\Carbon::parse($dueTime)->format('g:i A') : '' }}
                                </p>
                            @endif
                            <p style="font-size:12px; color:#94a3b8; line-height:1.6; margin:24px 0 0;">
                                You're receiving this because someone added this email to a task on RichWorks Performix. This address isn't linked to a Performix account.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
