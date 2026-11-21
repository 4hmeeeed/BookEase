@props(['booking'])

{{--
    A confirmed stay whose dates ended without a check-in. The staff "arrivals" list keeps
    these (check_out <= today) so the desk can cancel them; this badge says why they're there.
--}}
@if ($booking->status === \App\Enums\BookingStatus::Confirmed && $booking->check_out->lte(today()))
    <span {{ $attributes->merge(['class' => 'badge badge-cancelled']) }} title="The stay ended without a check-in">
        <span class="badge-dot"></span>No-show
    </span>
@endif
