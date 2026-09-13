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
            font-size: 18px;
            margin-bottom: 4px;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        table.summary td {
            padding: 4px 0;
            vertical-align: top;
        }

        table.summary td.from {
            width: 50%;
        }

        table.summary .label {
            font-weight: bold;
            width: 100px;
            display: inline-block;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.lines td, table.lines th {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
        }

        table.lines th {
            background-color: #f9fafb;
        }

        table.lines td.numeric, table.lines th.numeric {
            text-align: right;
        }

        table.totals {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }

        table.totals td {
            padding: 4px 8px;
        }

        table.totals td.numeric {
            text-align: right;
        }

        table.totals tr.total td {
            font-weight: bold;
            border-top: 1px solid #1f2937;
        }
    </style>
</head>
<body>
    <header>
        <img src="{{ $logoDataUri }}" alt="{{ $company->name }}">
    </header>

    <footer>
        {{ $company->trading_name ?? $company->name }} &mdash; Invoice {{ $invoice->number }}
    </footer>

    <h1>Invoice {{ $invoice->number }}</h1>

    <table class="summary">
        <tr>
            <td class="from">
                <strong>{{ $company->trading_name ?? $company->name }}</strong><br>
                @if ($company->legal_name)
                    {{ $company->legal_name }}<br>
                @endif
                @if ($company->company_number)
                    Company No: {{ $company->company_number }}<br>
                @endif
                @if ($company->vat_registration_number)
                    VAT No: {{ $company->vat_registration_number }}<br>
                @endif
                @if ($company->phone)
                    {{ $company->phone }}<br>
                @endif
            </td>
            <td>
                <span class="label">Invoice To:</span><br>
                <strong>{{ $client->name }}</strong><br>
                @if ($client->address)
                    {{ $client->address }}<br>
                @endif
                {{ collect([$client->city, $client->county, $client->postcode])->filter()->implode(', ') }}
                @if ($client->vat_registration_number)
                    <br>VAT No: {{ $client->vat_registration_number }}
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Invoice Date:</span> {{ $invoice->generated_at->format('d/m/Y') }}<br>
                <span class="label">Period:</span> {{ $invoice->period_start->format('d/m/Y') }} - {{ $invoice->period_end->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr>
            <th>Description</th>
            <th class="numeric">Days</th>
            <th class="numeric">Rate</th>
            <th class="numeric">Amount</th>
        </tr>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="numeric">{{ number_format($line->quantity, 2) }}</td>
                <td class="numeric">£{{ number_format($line->unit_rate, 2) }}</td>
                <td class="numeric">£{{ number_format($line->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="numeric">£{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>VAT</td>
            <td class="numeric">£{{ number_format($invoice->vat_amount, 2) }}</td>
        </tr>
        <tr class="total">
            <td>Total Due</td>
            <td class="numeric">£{{ number_format($invoice->total, 2) }}</td>
        </tr>
    </table>

    @if ($company->bank_account_number)
        <p style="margin-top: 30px;">
            <strong>Payment Details:</strong><br>
            {{ $company->bank_name }} &mdash; {{ $company->bank_account_name }}<br>
            Account: {{ $company->bank_account_number }} &mdash; Sort Code: {{ $company->bank_sort_code }}
        </p>
    @endif
</body>
</html>
