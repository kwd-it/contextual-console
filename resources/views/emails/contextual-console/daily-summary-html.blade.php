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
                                            <p style="margin:0 0 8px 0;font-size:13px;color:#333333;">
                                                <strong>Changes:</strong>
                                                added={{ $source->added }}
                                                removed={{ $source->removed }}
                                                changed={{ $source->changed }}
                                                unchanged={{ $source->unchanged }}
                                            </p>
                                            <p style="margin:0 0 12px 0;font-size:13px;color:#333333;">
                                                <strong>Active issues:</strong>
                                                {{ $source->activeIssueCount }}
                                                (errors={{ $source->errorCount }},
                                                warnings={{ $source->warningCount }},
                                                info={{ $source->infoCount }})
                                            </p>
                                            @if (count($source->issues) > 0)
                                                <ul style="margin:0;padding:0 0 0 18px;font-size:13px;color:#333333;">
                                                    @foreach ($source->issues as $issue)
                                                        <li style="margin:0 0 6px 0;">
                                                            <span style="font-weight:bold;text-transform:lowercase;">[{{ $issue->severity }}]</span>
                                                            @if ($issue->issueType !== null)
                                                                {{ $issue->issueType }}:
                                                            @endif
                                                            {{ $issue->message }}
                                                            @if ($issue->transition !== null)
                                                                <span style="color:#555555;"> ({{ $issue->transition }})</span>
                                                            @endif
                                                            @if ($issue->suffix !== '')
                                                                <span style="color:#555555;"> {{ $issue->suffix }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
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
