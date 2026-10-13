<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#fcf9f2">
        <meta name="description" content="Memory Master — watch the sequence, repeat it from memory and climb the Meeple Mania leaderboards.">
        <link rel="icon" type="image/png" href="/images/meeplemania-logo.png">
        <title inertia>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
