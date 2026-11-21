@props(['url'])
{{-- BookEase wordmark (text, not an image: images are often blocked in mail clients). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{ config('app.name', 'BookEase') }}
<span class="tagline">Labnasan Resort</span>
</a>
</td>
</tr>
