<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $siteIdentity?->site_name ?? config('app.name', 'Car Rental') }}</title>

        @php
            $siteIdentity = \App\Models\SiteIdentity::first();
            $faviconUrl = $siteIdentity?->favicon_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($siteIdentity->favicon_path)
                : null;
        @endphp
        @if($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|inter:400,500,600|jetbrains-mono:400,500|poppins:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Active theme id — read by resources/theme/active.ts -->
        <script>window.__THEME__ = @json(config('site.active_theme', 'default'));</script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
