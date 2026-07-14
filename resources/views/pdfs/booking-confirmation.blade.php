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

        .intro {
            clear: both;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.summary td {
            padding: 3px 0;
            vertical-align: top;
        }

        table.summary td.label {
            font-weight: bold;
            width: 90px;
        }

        table.checks {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        table.checks td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            width: 25%;
            font-size: 10px;
        }

        table.checks td.label {
            font-weight: bold;
            background-color: #f9fafb;
            width: 25%;
        }

        h2 {
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 6px;
        }

        table.rates {
            width: 100%;
            border-collapse: collapse;
        }

        table.rates td, table.rates th {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
        }

        table.rates th {
            background-color: #f9fafb;
        }

        .summary-wrapper {
            width: 100%;
        }

        .summary-wrapper .summary {
            width: 75%;
            float: left;
        }

        .photo {
            width: 25%;
            float: left;
            text-align: right;
        }

        .photo img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <header>
        <img src="{{ public_path('images/applebough.png') }}" alt="{{ config('app.name') }}">
    </header>

    <footer>
        {{ config('app.name') }} &mdash; Booking Confirmation
    </footer>

    <h1>Booking Confirmation</h1>

    <div class="summary-wrapper">
        <table class="summary">
            <tr>
                <td class="label">Name:</td>
                <td>{{ trim("{$candidate->title} {$candidate->first_name} {$candidate->last_name}") }}</td>
            </tr>
            <tr>
                <td class="label">Job Title:</td>
                <td>{{ $booking->jobTitle?->name }}</td>
            </tr>
            <tr>
                <td class="label">Our Ref No:</td>
                <td>{{ $candidate->id }}</td>
            </tr>
            <tr>
                <td class="label">Booking Ref No:</td>
                <td>{{ $booking->id }}</td>
            </tr>
        </table>

        @if ($photoPath)
            <div class="photo">
                <img src="{{ $photoPath }}" alt="{{ trim("{$candidate->first_name} {$candidate->last_name}") }}">
            </div>
        @endif
    </div>

    <div class="intro">
        For all temporary staff placed by {{ config('app.name') }}, the following checks will be completed prior to
        the candidate being cleared to start work. I can confirm that {{ config('app.name') }} have completed the
        following checks.
    </div>

    <h2>Candidate Checks</h2>

    <table class="checks">
        @foreach ($checks->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $check)
                    <td class="label">{{ $check['label'] }}</td>
                    <td>{{ $check['value'] }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if ($bookingDates->isNotEmpty())
        <h2>Booking Date(s)</h2>

        <table class="rates">
            <tr>
                <th>Booking Date</th>
                <th>Type</th>
                <th>Start</th>
                <th>Charge Rate</th>
            </tr>
            @foreach ($bookingDates as $row)
                <tr>
                    <td>{{ $row['date']->format('d/m/Y') }}</td>
                    <td>{{ $row['period']->label() }}</td>
                    <td>{{ $row['start'] }}</td>
                    <td>{{ $row['rate'] !== null ? '£'.number_format($row['rate'], 2) : '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
