<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f0f2f7; padding:32px 0; margin:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(90deg,#1A0A0A,#7A0019); padding:24px 32px;">
                            <span style="display:block; color:#fff; font-weight:900; font-size:16px;">RichWorks Performix</span>
                            <span style="display:block; color:#f0c9c9; font-weight:700; font-size:11px; margin-top:3px; letter-spacing:.05em; text-transform:uppercase;">TTD — Things To Do</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:15px; color:#1e293b; margin:0 0 16px;">Hi,</p>
                            <p style="font-size:15px; color:#1e293b; line-height:1.6; margin:0 0 16px;">
                                <strong>{{ $creatorName }}</strong> assigned you a task on <strong>TTD (Things To Do)</strong> — the task tracker inside RichWorks Performix. Everything you need is below, no login required.
                            </p>
                            <p style="font-size:17px; font-weight:700; color:#1e293b; margin:0 0 10px;">{{ $taskTitle }}</p>
                            @if($taskDescription)
                                <p style="font-size:14px; color:#475569; line-height:1.6; margin:0 0 16px;">{{ $taskDescription }}</p>
                            @endif
                            <table cellpadding="0" cellspacing="0" style="width:100%; background:#f8fafc; border-radius:12px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:14px 16px 8px; font-size:12px; color:#64748b; width:38%;">Priority</td>
                                    <td style="padding:14px 16px 8px; font-size:13px; color:#1e293b; font-weight:700; text-transform:capitalize;">{{ $priority }}</td>
                                </tr>
                                @if($dueDate)
                                    <tr>
                                        <td style="padding:0 16px 8px; font-size:12px; color:#64748b;">Due</td>
                                        <td style="padding:0 16px 8px; font-size:13px; color:#1e293b; font-weight:700;">
                                            {{ \Illuminate\Support\Carbon::parse($dueDate)->format('j M Y') }}{{ $dueTime ? ' at ' . \Illuminate\Support\Carbon::parse($dueTime)->format('g:i A') : '' }}
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:0 16px 14px; font-size:12px; color:#64748b;">Assigned by</td>
                                    <td style="padding:0 16px 14px; font-size:13px; color:#1e293b; font-weight:700;">{{ $creatorName }}</td>
                                </tr>
                            </table>
                            <p style="font-size:12px; color:#94a3b8; line-height:1.6; margin:0;">
                                You're receiving this because someone on RichWorks Performix added this email address to a TTD (Things To Do) task. This address isn't linked to a Performix account — this email is the complete task detail, there's nothing further to open.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
