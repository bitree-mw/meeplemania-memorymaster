<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#fcf9f2">
        <meta name="description" content="Five quick memory games, three ways to play each. Climb levels, race the clock and take on the daily challenge on the MeepleMania leaderboards.">
        <link rel="icon" type="image/png" href="/images/meeplemania-logo.png">
        <title inertia>MeepleMania Games</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
