<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Areterra's 4-week finance period.
 *
 * Every part of the Hub that needs to know "which period is this?", "how many
 * operating days are in it?" or "what is this weekly cost in 4-weekly terms?"
 * asks this class. Nothing recalculates it locally.
 *
 * Periods are indexed from a fixed anchor date (config/periods.php). Index 0 is
 * the period beginning on the anchor; indexes run negative before it, so history
 * still resolves correctly.
 */
final class Period
{
    /** A finance period is always 28 days, inclusive of both ends. */
    public const LENGTH_DAYS = 28;

    /** Frequencies accepted by toFourWeekly()/fromFourWeekly(). */
    public const FREQUENCIES = ['weekly', 'fortnightly', 'four_weekly', 'monthly', 'quarterly', 'annually', 'one_off'];

    private function __construct(
        public readonly int $index,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    // ---------------------------------------------------------------- anchors

    /**
     * The first day of period 0. Set this once in config/periods.php to the
     * Monday your current 4-week finance cycle started, and never move it —
     * moving it renumbers every period in the system.
     */
    public static function anchor(): CarbonImmutable
    {
        $anchor = config('periods.anchor');

        if (blank($anchor)) {
            throw new InvalidArgumentException(
                'No finance period anchor configured. Set PERIOD_ANCHOR in .env to the start date of a known 4-week period.'
            );
        }

        return CarbonImmutable::parse($anchor)->startOfDay();
    }

    // ------------------------------------------------------------- factories

    public static function atIndex(int $index): self
    {
        $start = self::anchor()->addDays($index * self::LENGTH_DAYS);

        return new self($index, $start, $start->addDays(self::LENGTH_DAYS - 1)->endOfDay());
    }

    public static function containing(CarbonInterface|string|null $date = null): self
    {
        $date = $date === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse($date)->startOfDay();

        // intdiv() truncates toward zero, which is wrong for dates before the
        // anchor. floor() keeps negative indexes contiguous.
        $offset = self::anchor()->diffInDays($date, false);

        return self::atIndex((int) floor($offset / self::LENGTH_DAYS));
    }

    public static function current(): self
    {
        return self::containing();
    }

    // ------------------------------------------------------------ navigation

    public function plus(int $periods): self
    {
        return self::atIndex($this->index + $periods);
    }

    public function next(): self
    {
        return $this->plus(1);
    }

    public function previous(): self
    {
        return $this->plus(-1);
    }

    /**
     * Every period from $this up to and including $other, ascending.
     *
     * @return array<int, self>
     */
    public function through(self $other): array
    {
        [$from, $to] = $this->index <= $other->index ? [$this->index, $other->index] : [$other->index, $this->index];

        return array_map(static fn (int $i) => self::atIndex($i), range($from, $to));
    }

    // --------------------------------------------------------------- queries

    public function contains(CarbonInterface|string $date): bool
    {
        $date = CarbonImmutable::parse($date);

        return $date->betweenIncluded($this->start, $this->end);
    }

    public function isCurrent(): bool
    {
        return $this->contains(CarbonImmutable::today());
    }

    /** Days Areterra is actually open in this period — see OperatingDays. */
    public function operatingDays(): int
    {
        $count = 0;

        for ($day = $this->start; $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
            if (OperatingDays::isOperatingDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Operating days in this period for one weekday, e.g. how many Tuesdays a
     * Tuesday-only member is expected to attend. Used by attendance-driven
     * income forecasting.
     */
    public function operatingDaysFor(int $isoWeekday): int
    {
        if (! in_array($isoWeekday, OperatingDays::DAYS, true)) {
            return 0;
        }

        $count = 0;

        for ($day = $this->start; $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
            if ($day->isoWeekday() === $isoWeekday) {
                $count++;
            }
        }

        return $count;
    }

    public function label(): string
    {
        return $this->start->format('j M').' – '.$this->end->format('j M Y');
    }

    // ------------------------------------------------- frequency conversion

    /**
     * Normalise a recurring amount to its 4-weekly equivalent.
     *
     * There are 13 four-week periods in a year, which is why monthly figures are
     * ×12/13 rather than simply ×1. This is the single definition — FinanceController
     * previously carried a private copy.
     */
    public static function toFourWeekly(float $amount, string $frequency): float
    {
        return match ($frequency) {
            'weekly' => $amount * 4,
            'fortnightly' => $amount * 2,
            'four_weekly' => $amount,
            'monthly' => $amount * 12 / 13,
            'quarterly' => $amount * 4 / 13,
            'annually' => $amount / 13,
            'one_off' => $amount,
            default => throw new InvalidArgumentException("Unknown frequency [{$frequency}]."),
        };
    }

    /** Inverse of toFourWeekly() — for displaying a 4-weekly figure in another cadence. */
    public static function fromFourWeekly(float $amount, string $frequency): float
    {
        return match ($frequency) {
            'weekly' => $amount / 4,
            'fortnightly' => $amount / 2,
            'four_weekly', 'one_off' => $amount,
            'monthly' => $amount * 13 / 12,
            'quarterly' => $amount * 13 / 4,
            'annually' => $amount * 13,
            default => throw new InvalidArgumentException("Unknown frequency [{$frequency}]."),
        };
    }

    // ---------------------------------------------------------- serialisation

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'label' => $this->label(),
            'start_date' => $this->start->toDateString(),
            'end_date' => $this->end->toDateString(),
            'operating_days' => $this->operatingDays(),
            'is_current' => $this->isCurrent(),
        ];
    }
}
