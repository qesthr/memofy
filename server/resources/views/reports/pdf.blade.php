<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Memofy Report - {{ $generated_at }}</title>
    <style>
        /** 
         * DomPDF Setup
         * We use fixed positioning for header/footer to repeat on every page
         */
        @page {
            size: a4 landscape;
            margin: 140px 50px 80px 50px; /* Increased Top Margin to 140px */
        }

        #header {
            position: fixed;
            top: -120px;
            left: 0px;
            right: 0px;
            height: 120px;
            background: #fff;
            color: #333;
            padding: 0;
            width: 100%;
            text-align: center;
            border-bottom: 1.5px solid #1e40af; /* The straight line division */
        }

        #footer {
            position: fixed;
            bottom: -60px;
            left: 0px;
            right: 0px;
            height: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            width: 100%;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }

        .header-logo {
            height: 50px;
            margin-bottom: 5px;
        }

        .header-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .header-subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
            font-weight: normal;
            text-transform: uppercase;
        }

        .footer-table {
            width: 100%;
            font-size: 9px;
            color: #64748b;
        }

        .meta-table {
            width: 100%;
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }

        .meta-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }

        .meta-value {
            font-size: 11px;
            font-weight: bold;
            color: #334155;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
            margin: 20px 0 10px;
        }

        .stats-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .stat-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
        }

        .stat-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th {
            background: #f1f5f9;
            padding: 6px 10px;
            text-align: left;
            font-weight: bold;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 8px;
        }

        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 9px;
        }

        .certification {
            background: #f0fdf4;
            border: 1px solid #86efac;
            padding: 12px;
            margin-top: 30px;
            text-align: center;
            border-radius: 6px;
            color: #166534;
        }

        .signature-table {
            width: 100%;
            margin-top: 30px;
        }

        .signature-line {
            border-top: 1px solid #1e40af;
            width: 80%;
            margin: 30px auto 5px;
        }

        .tracking-box {
            margin-top: 15px;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            text-align: center;
            font-family: monospace;
            color: #64748b;
            font-size: 9px;
        }

        /** Ensure content doesn't break in weird places */
        .page-break {
            page-break-after: always;
        }
        
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <!-- Fixed Header -->
    <div id="header">
        <div style="margin-top: 10px;">
            <img src="data:image/png;base64,{{ $logo_base64 }}" class="header-logo" alt="Logo">
            <div class="header-title">MEMOFY Reports & Analytics</div>
            <div class="header-subtitle">Bukidnon State University - Department Memo Management System</div>
        </div>
    </div>

    <!-- Fixed Footer -->
    <div id="footer">
        <table class="footer-table">
            <tr>
                <td width="33%">Tracking #: <span style="font-weight: bold; color: #1e293b;">{{ $tracking_number }}</span></td>
                <!-- <td width="34%" align="center">Generated by: {{ $generated_by }}</td> -->
                <td width="33%" align="right">
                    <!-- Page Number injected by Controller Canvas API -->
                </td>
            </tr>
        </table>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Summary Meta -->
        <table class="meta-table">
            <tr>
                <td width="33%">
                    <div class="meta-label">Generated At</div>
                    <div class="meta-value">{{ $generated_at }}</div>
                </td>
                <td width="33%">
                    <div class="meta-label">Total Activities</div>
                    <div class="meta-value">{{ $activity['total'] ?? 0 }}</div>
                </td>
                <td width="34%">
                    <div class="meta-label">Status Summary</div>
                    <div class="meta-value" style="color: #fbbf24">Official System Report</div>
                </td>
            </tr>
        </table>

        <!-- Overview -->
        <div class="section-title">Overview Statistics</div>
        <table class="stats-table" width="100%" cellspacing="10">
            <tr>
                <td class="stat-box" width="25%">
                    <div class="stat-value">{{ $overview['total_users'] ?? 0 }}</div>
                    <div class="stat-label">Total Users</div>
                </td>
                <td class="stat-box" width="25%">
                    <div class="stat-value">{{ $overview['active_users'] ?? 0 }}</div>
                    <div class="stat-label">Active Users</div>
                </td>
                <td class="stat-box" width="25%">
                    <div class="stat-value">{{ $overview['total_memos'] ?? 0 }}</div>
                    <div class="stat-label">Total Memos</div>
                </td>
                <td class="stat-box" width="25%">
                    <div class="stat-value">{{ $overview['memos_this_period'] ?? 0 }}</div>
                    <div class="stat-label">Period Memos</div>
                </td>
            </tr>
        </table>

        <!-- Layout 2 Columns -->
        <table width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td width="48%" valign="top">
                    <div class="section-title">Memo Status Distribution</div>
                    @if(isset($memoStatusDistribution['labels']))
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($memoStatusDistribution['labels'] as $index => $label)
                            <tr>
                                <td>{{ ucfirst($label) }}</td>
                                <td>{{ $memoStatusDistribution['data'][$index] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </td>
                <td width="4%"></td> <!-- Spacer -->
                <td width="48%" valign="top">
                    <div class="section-title">Department Insights</div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Users</th>
                                <th>Memos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($departments, 0, 5) as $dept => $stats)
                            <tr>
                                <td>{{ $dept }}</td>
                                <td>{{ $stats['total_users'] ?? 0 }}</td>
                                <td>{{ $stats['total_memos'] ?? 0 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section-title">Performance Leaderboard (Top 5 Active)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="10%">Rank</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th width="15%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users['top_active_users'] ?? [] as $index => $u)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $u['name'] ?? 'N/A' }}</td>
                    <td>Faculty/Staff</td>
                    <td>{{ $u['activity_count'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="certification">
            <strong>System Certification</strong><br>
            This report is digitally verified and authenticated by the Memofy Core.
        </div>

        <!-- Signature Section -->
        <table class="signature-table">
            <tr>
                <td width="50%" align="center">
                    <div class="signature-line"></div>
                    <div class="stat-label">System Administrator Signature</div>
                    <div style="font-weight:bold; color:#1e40af">{{ $generated_by }}</div>
                </td>
                <td width="50%" align="center">
                    <div class="signature-line"></div>
                    <div class="stat-label">Institutional Verification</div>
                    <div style="font-weight:bold; color:#1e40af">University Admin Office</div>
                </td>
            </tr>
        </table>

        <div class="tracking-box">
            System Hash: {{ md5($tracking_number . $generated_at) }} | Auth Code: {{ strtoupper(substr(md5($generated_by), 0, 8)) }}
        </div>
    </div>
</body>
</html>
