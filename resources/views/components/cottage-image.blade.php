@props(['cottage', 'morph' => false])

{{--
    Uploaded photo, or a chart-style placeholder (buff "land", hairline contours).
    `morph` names the image for the cross-page view transition (card → cottage page);
    use it at most once per page for a given cottage.
--}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-sand-100']) }}
     @if ($morph) style="view-transition-name: cottage-photo-{{ $cottage->id }}" @endif>
    @if ($cottage->image_url)
        {{-- Shimmer shows while the photo loads (slow on `php artisan serve`); hover zoom lives in app.css. --}}
        <div class="photo-shimmer absolute inset-0" aria-hidden="true"></div>
        <img src="{{ $cottage->image_url }}" alt="{{ $cottage->name }}" loading="lazy" decoding="async"
             onload="this.previousElementSibling?.remove()" onerror="this.previousElementSibling?.remove()"
             class="tilt-photo relative h-full w-full object-cover">
    @else
        <div class="absolute inset-0 bg-[linear-gradient(160deg,theme(colors.sand.100),theme(colors.sand.200))]" aria-hidden="true"></div>
        <svg class="absolute inset-0 h-full w-full text-sand-500/50" viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M-20 210c60-30 110 10 170-12s90-70 160-58 90 40 110 30" stroke-width="1" />
            <path d="M-20 236c70-26 120 12 180-8s100-62 170-48 80 34 100 26" stroke-width=".8" />
            <path d="M-20 262c80-22 130 12 190-4s110-50 180-40 70 26 80 22" stroke-width=".6" />
            <path d="M120 120c20-26 70-30 96-8s18 52-14 60-96-24-82-52Z" stroke-width=".8" />
            <path d="M138 122c14-16 50-18 66-4s10 34-10 38-66-16-56-34Z" stroke-width=".6" />
        </svg>
        <span class="sounding absolute bottom-3 left-3 text-sand-700">Photo to come</span>
    @endif
</div>
