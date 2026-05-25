<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#222222;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f4f4;">
    <tr>
        <td align="center" style="padding:16px 12px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #dddddd;border-radius:4px;">
                <tr>
                    <td style="padding:20px 24px 8px 24px;">
                        <p style="margin:0 0 8px 0;font-size:12px;color:#666666;letter-spacing:0.02em;">Contextual Console</p>
                        <h1 style="margin:0 0 12px 0;font-size:20px;font-weight:bold;color:#111111;">{{ $report->title }}</h1>
                        @if ($report->periodLabel !== null)
                            <p style="margin:0 0 16px 0;font-size:14px;color:#444444;">{{ $report->periodLabel }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 24px 24px;">
                        @if ($report->emptyMessage !== null)
                            <p style="margin:0;font-size:14px;color:#444444;">{{ $report->emptyMessage }}</p>
                        @else
                            @foreach ($report->sources as $source)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:16px;border:1px solid #e0e0e0;border-radius:4px;">
                                    <tr>
                                        <td style="padding:14px 16px;background-color:#f9f9f9;border-bottom:1px solid #e0e0e0;">
                                            <p style="margin:0 0 4px 0;font-size:16px;font-weight:bold;color:#111111;">{{ $source->name }}</p>
                                            <p style="margin:0;font-size:12px;color:#666666;">Source key: {{ $source->sourceKey }}</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:14px 16px;">
                                            <p style="margin:0 0 8px 0;font-size:13px;color:#333333;">
                                                <strong>Latest run in period:</strong>
                                                #{{ $source->periodRunId }} {{ $source->periodRunStatus }}
                                            </p>
                                            <p style="margin:0 0 12px 0;font-size:13px;color:#555555;">
                                                Finished {{ $source->periodRunFinishedAt }}
                                            </p>
                                            @if ($source->overallRunId !== null)
                                                <p style="margin:0 0 12px 0;font-size:13px;color:#333333;">
                                                    <strong>Overall latest run:</strong>
                                                    #{{ $source->overallRunId }} {{ $source->overallRunStatus }}
                                                    (finished {{ $source->overallRunFinishedAt }})
                                                </p>
                                            @endif
                                            @if ($source->recoveryNote !== '')
                                                <p style="margin:0 0 12px 0;padding:8px 10px;font-size:13px;color:#333333;background-color:#fff8e6;border:1px solid #e6d9a8;border-radius:3px;">
                                                    {{ $source->recoveryNote }}
                                                </p>
                                            @endif

                                            <p style="margin:0 0 6px 0;font-size:12px;font-weight:bold;color:#444444;text-transform:uppercase;letter-spacing:0.03em;">Changes</p>
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 14px 0;border:1px solid #e0e0e0;border-collapse:collapse;">
                                                <tr>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Added</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Removed</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Changed</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Unchanged</th>
                                                </tr>
                                                <tr>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->added }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->removed }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->changed }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->unchanged }}</td>
                                                </tr>
                                            </table>

                                            <p style="margin:0 0 6px 0;font-size:12px;font-weight:bold;color:#444444;text-transform:uppercase;letter-spacing:0.03em;">Active issues</p>
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 14px 0;border:1px solid #e0e0e0;border-collapse:collapse;">
                                                <tr>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Errors</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Warnings</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Info</th>
                                                    <th align="center" style="padding:8px 6px;font-size:11px;font-weight:bold;color:#555555;background-color:#f5f5f5;border:1px solid #e0e0e0;">Total active issues</th>
                                                </tr>
                                                <tr>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->errorCount }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->warningCount }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->infoCount }}</td>
                                                    <td align="center" style="padding:10px 6px;font-size:15px;font-weight:bold;color:#111111;border:1px solid #e0e0e0;">{{ $source->activeIssueCount }}</td>
                                                </tr>
                                            </table>

                                            @if (count($source->issues) > 0)
                                                <p style="margin:0 0 8px 0;font-size:12px;font-weight:bold;color:#444444;text-transform:uppercase;letter-spacing:0.03em;">Issue details</p>
                                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;border:1px solid #e0e0e0;border-collapse:collapse;">
                                                    @foreach ($source->issues as $issue)
                                                        <tr>
                                                            <td valign="top" width="72" style="padding:10px 8px;font-size:11px;font-weight:bold;text-transform:uppercase;vertical-align:top;border-top:1px solid #e0e0e0;background-color:#fafafa;@if ($issue->severity === 'error') color:#8b2e2e; @elseif ($issue->severity === 'warning') color:#7a5c00; @else color:#444444; @endif">
                                                                {{ $issue->severity }}
                                                            </td>
                                                            <td style="padding:10px 10px 10px 0;font-size:13px;color:#333333;vertical-align:top;border-top:1px solid #e0e0e0;">
                                                                @if ($issue->issueType !== null)
                                                                    <p style="margin:0 0 4px 0;font-size:12px;color:#666666;">{{ $issue->issueType }}</p>
                                                                @endif
                                                                <p style="margin:0 0 4px 0;font-size:13px;color:#111111;">{{ $issue->message }}</p>
                                                                @if ($issue->transition !== null)
                                                                    <p style="margin:0 0 4px 0;font-size:12px;color:#555555;">{{ $issue->transition }}</p>
                                                                @endif
                                                                @if ($issue->suffix !== '')
                                                                    <p style="margin:0;font-size:12px;color:#555555;font-style:italic;">{{ $issue->suffix }}</p>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            @endforeach
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
