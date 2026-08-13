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

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        h3 {
            font-size: 12px;
            margin-top: 14px;
            margin-bottom: 4px;
        }

        p, li {
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <header>
        <img src="{{ $logoDataUri }}" alt="{{ config('app.name') }}">
    </header>

    <h1>{{ $name }}</h1>

    {!! $bodyHtml !!}
</body>
</html>
