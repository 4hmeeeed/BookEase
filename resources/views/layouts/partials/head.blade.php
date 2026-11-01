<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1e5756">
<meta name="view-transition" content="same-origin">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'BookEase') }} — Labnasan Resort</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700|hanken-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
