<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 130px 40px 60px 40px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        header {
            position: fixed;
            top: -110px;
            left: 0px;
            right: 0px;
            height: 100px;
            border-bottom: 2px solid #16a34a;
            padding-bottom: 10px;
        }

        header img {
            height: 60px;
        }

        footer {
            position: fixed;
            bottom: -50px;
            left: 0px;
            right: 0px;
            height: 40px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }

        h1 {
            font-size: 15px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 6px;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            background-color: #f3f4f6;
        }

        table.summary td {
            padding: 6px 10px;
            vertical-align: top;
        }

        table.summary td.label {
            font-weight: bold;
            width: 130px;
        }

        table.rates {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.rates td, table.rates th {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
        }

        table.rates th {
            background-color: #f9fafb;
        }

        .signature {
            margin-top: 6px;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .pending {
            color: #b45309;
        }
    </style>
</head>
<body>
    <header>
        <img src="{{ $logoDataUri }}" alt="{{ config('app.name') }}">
    </header>

    <footer>
        {{ config('app.name') }} &mdash; Weekly Timesheet
    </footer>

    <h1>Weekly Timesheet</h1>

    <table class="summary">
        <tr>
            <td class="label">Client Name:</td>
            <td>{{ $client->name }}</td>
        </tr>
        <tr>
            <td class="label">Timesheet Week:</td>
            <td>{{ $period['start']->format('d/m/Y') }} - {{ $period['end']->format('d/m/Y') }}</td>
        </tr>
    </table>

    @foreach ($contractors as $contractor)
        <h2>Contractor: {{ $contractor['name'] }}</h2>

        <table class="rates">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Job Title</th>
                <th>Charge Rate</th>
            </tr>
            @foreach ($contractor['rows'] as $row)
                <tr>
                    <td>{{ $row['date']->format('D, d/m/Y') }}</td>
                    <td>{{ $row['period']->label() }}</td>
                    <td>{{ $row['job_title'] ?? '—' }}</td>
                    <td>{{ $row['rate'] !== null ? '£'.number_format($row['rate'], 2) : '—' }}</td>
                </tr>
            @endforeach
        </table>

        <div class="signature">
            @if ($contractor['approval'])
                Name : {{ $contractor['approval']['name'] }}<br>
                Date : {{ $contractor['approval']['date']->format('d-m-Y') }}<br>
                Signature : Approved by {{ $contractor['approval']['name'] }} at {{ $contractor['approval']['date']->format('d-m-Y H:i') }}
            @else
                <span class="pending">Awaiting client approval for these days.</span>
            @endif
        </div>
    @endforeach
</body>
</html>
