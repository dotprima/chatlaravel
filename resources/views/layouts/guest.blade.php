<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="{{ config('app.name', 'Laravel') }}">
        <meta name="description" content="Asisten AI untuk Pengadilan Agama Cirebon yang membantu mempermudah proses hukum dan memberikan informasi yang akurat dan cepat.">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ request()->url() }}">
        <meta property="og:title" content="{{ config('app.name', 'Laravel') }}">
        <meta property="og:description" content="Asisten AI untuk Pengadilan Agama Cirebon yang membantu mempermudah proses hukum dan memberikan informasi yang akurat dan cepat.">
        <meta property="og:image" content="{{ asset('logo.png') }}">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ request()->url() }}">
        <meta property="twitter:title" content="{{ config('app.name', 'Laravel') }}">
        <meta property="twitter:description" content="Asisten AI untuk Pengadilan Agama Cirebon yang membantu mempermudah proses hukum dan memberikan informasi yang akurat dan cepat.">
        <meta property="twitter:image" content="{{ asset('logo.png') }}">

        <!-- Keywords -->
        <meta name="keywords" content="AI Assistant, Pengadilan Agama Cirebon, Hukum Agama, Asisten Hukum, Cirebon, Teknologi Hukum, Layanan Hukum AI">

        <!-- Author -->
        <meta name="author" content="Nama Anda atau Perusahaan Anda">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
