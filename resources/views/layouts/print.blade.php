<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'Zaico') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css'])

        <style>
            .carta {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @media print {
                @page {
                    size: letter;
                    margin: 14mm;
                }

                .no-print {
                    display: none !important;
                }

                body {
                    background: #fff !important;
                }

                .carta {
                    box-shadow: none !important;
                    border: 0 !important;
                }
            }
        </style>

        @stack('styles')
    </head>
    <body class="bg-slate-100 font-sans text-slate-900 antialiased">
        {{ $slot }}

        @stack('scripts')
    </body>
</html>
