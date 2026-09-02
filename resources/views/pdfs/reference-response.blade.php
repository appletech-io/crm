<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 40px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .logo {
            height: 50px;
            margin-bottom: 16px;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 2px 0;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 12px;
            margin: 18px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.details td {
            padding: 3px 0;
            vertical-align: top;
        }

        table.details td.label {
            width: 160px;
            color: #6b7280;
        }

        .field {
            margin-bottom: 10px;
        }

        .field .label {
            color: #6b7280;
            margin-bottom: 2px;
        }

        .field .answer {
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <img src="{{ $logoDataUri }}" class="logo" alt="Logo">

    <h1>{{ $reference->type->label() }} Reference</h1>
    <div class="subtitle">For {{ $candidateName }}</div>

    <h2>Referee Details</h2>
    <table class="details">
        <tr>
            <td class="label">Name</td>
            <td>{{ $refereeName ?: '—' }}</td>
        </tr>
        @if ($reference->job_title)
            <tr>
                <td class="label">Job Title</td>
                <td>{{ $reference->job_title }}</td>
            </tr>
        @endif
        @if ($reference->email)
            <tr>
                <td class="label">Email</td>
                <td>{{ $reference->email }}</td>
            </tr>
        @endif
        @if ($reference->worked_from || $reference->worked_to)
            <tr>
                <td class="label">Dates Known / Worked</td>
                <td>{{ $reference->worked_from?->format('d M Y') ?? '—' }} to {{ $reference->worked_to?->format('d M Y') ?? '—' }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Submitted</td>
            <td>{{ $reference->submitted_at?->format('d M Y') ?? 'Not yet submitted' }}</td>
        </tr>
    </table>

    @foreach ($sections as $section)
        @if ($section['heading'])
            <h2>{{ $section['heading'] }}</h2>
        @endif

        @foreach ($section['fields'] as $field)
            @php
                $showWhen = $field['show_when'] ?? null;
                $isVisible = ! $showWhen || (($reference->answers[$showWhen[0]] ?? null) === $showWhen[1]);
            @endphp

            @if ($isVisible)
                @php
                    $rawAnswer = $reference->answers[$field['key']] ?? null;
                    $answer = match ($field['type']) {
                        'date' => $rawAnswer ? \Illuminate\Support\Carbon::parse($rawAnswer)->format('d M Y') : null,
                        'radio' => $field['options'][$rawAnswer] ?? $rawAnswer,
                        default => $rawAnswer,
                    };
                @endphp

                <div class="field">
                    <div class="label">{{ $field['label'] }}</div>
                    <div class="answer">{{ $answer ?: '—' }}</div>
                </div>
            @endif
        @endforeach
    @endforeach

    <h2>Confirmation</h2>
    <div class="field">
        <div class="label">Name</div>
        <div class="answer">{{ $reference->answers['confirm_name'] ?? '—' }}</div>
    </div>

    @if ($needsPositionAndOrganisation)
        <div class="field">
            <div class="label">Position</div>
            <div class="answer">{{ $reference->answers['confirm_position'] ?? '—' }}</div>
        </div>

        <div class="field">
            <div class="label">School / Organisation Name</div>
            <div class="answer">{{ $reference->answers['confirm_organisation'] ?? '—' }}</div>
        </div>
    @endif
</body>
</html>
