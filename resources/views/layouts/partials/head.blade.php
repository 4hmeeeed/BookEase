<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1e5756">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'BookEase') }} — Labnasan Resort</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700|hanken-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

{{-- A skipped cross-page transition rejects its promises; that is expected, not an error. --}}
<script>['pageswap','pagereveal'].forEach(function(n){addEventListener(n,function(e){var t=e.viewTransition;if(t){t.ready.catch(function(){});t.finished.catch(function(){});if(t.updateCallbackDone)t.updateCallbackDone.catch(function(){});}});});</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
