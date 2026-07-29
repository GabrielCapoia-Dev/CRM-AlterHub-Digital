<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $documento['titulo'] }} - {{ $documento['numero'] }}</title>
    <style>
        @page {
            margin: 104pt 26pt 51pt;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #171717;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            line-height: 1.36;
        }

        .document-header {
            height: 82px;
            margin-bottom: 14px;
            border-bottom: 2px solid #1f2937;
        }

        .document-footer {
            position: fixed;
            right: 0;
            bottom: -48px;
            left: 0;
            height: 32px;
            border-top: 1px solid #c9cdd2;
            color: #666;
            font-size: 7.5px;
            padding-top: 8px;
        }

        .header-table,
        .footer-table,
        .info-grid,
        .address-grid,
        .summary-grid,
        .photo-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            height: 78px;
            padding: 0;
            vertical-align: middle;
        }

        .brand-cell {
            width: 27%;
        }

        .company-cell {
            width: 40%;
            padding: 0 12px !important;
        }

        .document-cell {
            width: 33%;
            text-align: right;
        }

        .brand-logo {
            display: block;
            width: auto;
            max-width: 160px;
            height: auto;
            max-height: 54px;
        }

        .company-name {
            color: #1d4b91;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .company-legal,
        .company-cnpj,
        .company-extra {
            color: #555;
            font-size: 7.8px;
        }

        .document-title {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.15;
        }

        .document-number {
            font-size: 10px;
            font-weight: 700;
            margin-top: 3px;
        }

        .document-meta {
            color: #555;
            font-size: 8px;
            margin-top: 3px;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid #1f2937;
            border-radius: 4px;
            color: #1f2937;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .5px;
            margin-left: 4px;
            padding: 2px 5px;
            text-transform: uppercase;
        }

        .footer-table td {
            padding: 0;
            vertical-align: top;
        }

        .footer-company {
            width: 58%;
        }

        .footer-generated {
            width: 42%;
            padding-right: 130px !important;
            text-align: right;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        .section {
            margin-bottom: 14px;
        }

        .section-title {
            border-bottom: 1px solid #b8bcc1;
            color: #4b4f55;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .7px;
            margin: 0 0 8px;
            padding: 0 0 4px;
            text-transform: uppercase;
        }

        .label {
            color: #686c72;
            display: block;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .35px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .value {
            color: #171717;
            font-size: 9.5px;
        }

        .muted {
            color: #666;
        }

        .small {
            font-size: 7.5px;
        }

        .info-grid td,
        .address-grid td,
        .summary-grid td {
            padding: 0 10px 7px 0;
            vertical-align: top;
        }

        .data-table {
            border-collapse: collapse;
            margin-top: 3px;
            width: 100%;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .data-table th {
            background: #eef0f2;
            border: 1px solid #c9cdd2;
            color: #3f4348;
            font-size: 7.5px;
            letter-spacing: .25px;
            padding: 6px;
            text-align: left;
            text-transform: uppercase;
        }

        .data-table td {
            border: 1px solid #d5d8dc;
            padding: 6px;
            vertical-align: top;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .nowrap {
            white-space: nowrap;
        }

        .total-box {
            border-top: 2px solid #23262a;
            font-size: 10px;
            font-weight: 700;
            margin: 0 0 16px auto;
            padding: 8px 6px;
            text-align: right;
            width: 48%;
        }

        .total-box strong {
            font-size: 17px;
            margin-left: 8px;
        }

        .note-box {
            background: #f7f8f9;
            border-left: 3px solid #4d6ea9;
            padding: 8px 10px;
            white-space: pre-line;
        }

        .pdf-page {
            page-break-after: always;
        }

        .pdf-page-last {
            page-break-after: auto;
        }

        .avoid-break {
            page-break-inside: avoid;
        }
    </style>
    @stack('styles')
</head>
<body>
    @include('documentos.partials.rodape')

    <main>
        @yield('content')
    </main>
</body>
</html>
