<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#7c5cff">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="format-detection" content="telephone=no">
        <meta name="apple-mobile-web-app-title" content="DeutschTrainer">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/icons/favicon-32x32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/icons/favicon-16x16.png" sizes="16x16" type="image/png">
        <link rel="icon" href="/icons/icon-192.png" sizes="192x192" type="image/png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" sizes="180x180">

        <title inertia>{{ config('app.name', 'DeutschTrainer') }}</title>

        <script>
            // Apply the saved theme before first paint to avoid a flash.
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    var dark = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) {
                        document.documentElement.classList.add('dark');
                        var m = document.querySelector('meta[name="theme-color"]');
                        if (m) m.setAttribute('content', '#111827');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        @inertia
    </body>
</html>
