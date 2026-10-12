<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>COMING SOON! — {{ config('app.name') }}</title>

        @vite('resources/css/app.css')
    </head>
    <body class="bg-neutral-50 font-sans text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <main class="flex min-h-svh items-center justify-center p-6 text-center">
            <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl lg:text-8xl">COMING SOON!</h1>
        </main>
    </body>
</html>
