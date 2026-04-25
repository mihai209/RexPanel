<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $appBranding['brandName'] ?? 'RA-panel' }}</title>
        <link id="app-favicon" rel="icon" href="{{ $appBranding['faviconUrl'] ?? '/favicon.ico' }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])

        <script>
            window.RA_PANEL_BOOTSTRAP = {{ \Illuminate\Support\Js::from([
                'settings' => [
                    'brandName' => $appBranding['brandName'] ?? 'RA-panel',
                    'faviconUrl' => $appBranding['faviconUrl'] ?? '/favicon.ico',
                ],
                'uiWebsocket' => [
                    'host' => env('UI_WS_HOST', request()->getHost()),
                    'port' => (int) env('UI_WS_PORT', 8082),
                    'scheme' => env('UI_WS_SCHEME', request()->isSecure() ? 'wss' : 'ws'),
                ],
            ]) }};
        </script>
        
        <style>
            body { 
                background-color: #1a2035; 
                color: #e2e8f0;
                margin: 0;
                font-family: 'Inter', sans-serif;
            }
        </style>
    </head>
    <body class="antialiased">
        <div id="root"></div>
    </body>
</html>
