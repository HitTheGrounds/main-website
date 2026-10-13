<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $type === 'locked' ? 'Locked Teams' : 'All Teams' }} Export</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }

        .team-page {
            page-break-after: always;
            padding: 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .team-page:last-child {
            page-break-after: avoid;
        }

        .team-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }

        .team-name {
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .team-info {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 8px;
            margin-top: 15px;
        }

        .info-label {
            font-weight: bold;
            color: #333;
        }

        .info-value {
            color: #000;
        }

        .members-section {
            margin-top: 30px;
            flex-grow: 1;
        }

        .members-title {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 15px;
            color: #1a1a1a;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .members-table th {
            background-color: #f0f0f0;
            border: 1px solid #333;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 11pt;
        }

        .members-table td {
            border: 1px solid #666;
            padding: 10px;
            font-size: 11pt;
        }

        .members-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e0e0e0;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }

        .badge-captain {
            background-color: #ffd700;
            color: #000;
        }

        .gender-male {
            color: #0066cc;
        }

        .gender-female {
            color: #cc0066;
        }

        .footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .team-page {
                page-break-after: always;
            }

            .team-page:last-child {
                page-break-after: avoid;
            }

            .print-button {
                display: none !important;
            }

            @page {
                margin: 1cm;
                size: A4 portrait;
            }
        }

        @media screen {
            body {
                background: #e0e0e0;
                padding: 20px;
            }

            .team-page {
                background: white;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                max-width: 21cm;
                margin-left: auto;
                margin-right: auto;
            }

            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 24px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 14pt;
                cursor: pointer;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
            }

            .print-button:hover {
                background-color: #0056b3;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print / Save as PDF</button>

    @foreach($teams as $team)
        <div class="team-page">
            <div class="team-header">
                <div class="team-name">{{ $team->team_name }}</div>
                <div class="team-info">
                    <div class="info-label">Company:</div>
                    <div class="info-value">{{ $team->company?->name ?? 'N/A' }}</div>

                    <div class="info-label">Captain Name:</div>
                    <div class="info-value">{{ $team->captain()?->name ?? 'N/A' }}</div>

                    <div class="info-label">Captain Email:</div>
                    <div class="info-value">{{ $team->captain_email }}</div>

                    <div class="info-label">Phone Number:</div>
                    <div class="info-value">{{ $team->captain_phone }}</div>

                    <div class="info-label">Team Status:</div>
                    <div class="info-value">
                        @if($team->locked)
                            <span class="badge">Locked</span>
                        @endif
                        @if($team->approved)
                            <span class="badge">Approved</span>
                        @else
                            <span class="badge">Pending Approval</span>
                        @endif
                    </div>

                    <div class="info-label">Total Members:</div>
                    <div class="info-value">
                        {{ $team->members->count() }}
                        (Male: {{ $team->getMaleCount() }}, Female: {{ $team->getFemaleCount() }})
                    </div>
                </div>
            </div>

            <div class="members-section">
                <div class="members-title">Team Members</div>
                <table class="members-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Name</th>
                            <th style="width: 120px;">Gender</th>
                            <th style="width: 120px;">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($team->members as $index => $member)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $member->name }}</td>
                                <td class="{{ $member->gender === 'Male' ? 'gender-male' : 'gender-female' }}">
                                    {{ $member->gender }}
                                </td>
                                <td>
                                    @if($member->is_captain)
                                        <span class="badge badge-captain">Captain</span>
                                    @else
                                        Member
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="footer">
                Generated on {{ now()->format('F j, Y \a\t g:i A') }}
            </div>
        </div>
    @endforeach

    @if($teams->isEmpty())
        <div class="team-page">
            <div class="team-header">
                <div class="team-name">No Teams Found</div>
            </div>
            <div class="members-section">
                <p>There are no {{ $type === 'locked' ? 'locked' : '' }} teams to export.</p>
            </div>
        </div>
    @endif
</body>
</html>
