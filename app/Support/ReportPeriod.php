<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * An inclusive date range for reports: "Nov 1 – Nov 30" means every night
 * from Nov 1 up to and including the night of Nov 30. Internally it is the
 * half-open interval [from, to + 1 day), like bookings.
 */
final readonly class ReportPeriod
{
    public const MAX_DAYS = 731;

    public const PRESETS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30' => 'Last 30 days',
        'next_30' => 'Next 30 days',
        'this_year' => 'This year',
    ];

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $preset,
    ) {}

    public static function between(string|CarbonImmutable $from, string|CarbonImmutable $to, string $preset = 'custom'): self
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $to = $from->addDays(self::MAX_DAYS - 1);
        }

        return new self($from, $to, $preset);
    }

    public static function preset(string $name): self
    {
        $today = CarbonImmutable::today();

        return match ($name) {
            'last_month' => self::between($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), $name),
            'last_30' => self::between($today->subDays(29), $today, $name),
            'next_30' => self::between($today, $today->addDays(29), $name),
            'this_year' => self::between($today->startOfYear(), $today->endOfYear(), $name),
            default => self::between($today->startOfMonth(), $today->endOfMonth(), 'this_month'),
        };
    }

    public static function fromRequest(Request $request): self
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (is_string($from) && is_string($to) && self::isDate($from) && self::isDate($to)) {
            return self::between($from, $to);
        }

        return self::preset((string) $request->query('period', 'this_month'));
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** Exclusive end: the day after the last day in the range. */
    public function end(): CarbonImmutable
    {
        return $this->to->addDay();
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->end());
    }

    /** @return list<CarbonImmutable> */
    public function dates(): array
    {
        $dates = [];
        for ($d = $this->from; $d->lt($this->end()); $d = $d->addDay()) {
            $dates[] = $d;
        }

        return $dates;
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('M j, Y');
        }

        return $this->from->year === $this->to->year
            ? $this->from->format('M j').' – '.$this->to->format('M j, Y')
            : $this->from->format('M j, Y').' – '.$this->to->format('M j, Y');
    }

    /** @return array{from: string, to: string} */
    public function query(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
