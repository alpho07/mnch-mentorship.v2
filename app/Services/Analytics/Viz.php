<?php

namespace App\Services\Analytics;

/**
 * Turns numbers into the geometry the Blade views draw: bar widths, donut dashes,
 * SVG paths and axis ticks. Pure PHP, no framework calls, so it is easy to test.
 */
final class Viz
{
    public static function fmt(int|float|null $n, int $dp = 0): string
    {
        return $n === null ? '—' : number_format((float) $n, $dp);
    }

    /** "12.3%" style; keeps one decimal under 10 % and below 1 % so small shares stay visible. */
    public static function pct(int|float $n, int|float $d, ?int $dp = null): string
    {
        if (! $d) {
            return '0%';
        }
        $p = $n / $d * 100;
        if ($p > 0 && $p < 0.05) {
            return '<0.1%';
        }
        if ($dp === null) {
            $dp = ($p > 0 && $p < 10) ? 1 : 0;
        }

        return number_format($p, $dp).'%';
    }

    public static function delay(int $i, float $base = 0.15, float $step = 0.05): string
    {
        return number_format($base + $i * $step, 2).'s';
    }

    /** Round an axis maximum up to a friendly number (27 → 30, 243 → 250, 4 → 5). */
    public static function niceMax(float $max): float
    {
        if ($max <= 0) {
            return 1;
        }
        $mag = 10 ** floor(log10($max));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) {
            if ($m * $mag >= $max) {
                return $m * $mag;
            }
        }

        return 10 * $mag;
    }

    /**
     * Horizontal bar list. $pairs: label => value (already sorted).
     *
     * @return list<array{label:string, v:string, raw:int|float, w:string, color:string, delay:string}>
     */
    public static function bars(array $pairs, string $color, string $topColor): array
    {
        $max = max([1, ...array_values($pairs)]);
        $out = [];
        $i = 0;
        foreach ($pairs as $label => $v) {
            $out[] = [
                'label' => (string) $label,
                'v' => self::fmt($v),
                'raw' => $v,
                'w' => number_format(max(1.5, $v / $max * 100), 1).'%',
                'color' => $i === 0 ? $topColor : $color,
                'delay' => self::delay($i),
            ];
            $i++;
        }

        return $out;
    }

    /**
     * Donut segments for a circle of r = 15.9155 (circumference 100).
     * $segments: list of [label, value, color].
     */
    public static function donut(array $segments, ?int $total = null): array
    {
        $total ??= array_sum(array_column($segments, 1));
        $acc = 0.0;
        $out = [];
        foreach ($segments as $i => [$label, $value, $color]) {
            $len = $total ? $value / $total * 100 : 0;
            $vis = max(0, $len - 0.8);
            $out[] = [
                'label' => $label,
                'v' => self::fmt($value),
                'raw' => $value,
                'pct' => self::pct($value, $total, 0),
                'color' => $color,
                'dash' => number_format($vis, 2, '.', '').' '.number_format(100 - $vis, 2, '.', ''),
                'off' => number_format(-$acc, 2, '.', ''),
                'delay' => self::delay($i, 0.2, 0.15),
            ];
            $acc += $len;
        }

        return $out;
    }

    /**
     * Smooth line + area paths in a 1000×300 box, plus point positions in %.
     *
     * @param  array<string,int>  $series  label => value (oldest first)
     */
    public static function trend(array $series): array
    {
        $labels = array_keys($series);
        $vals = array_values($series);
        $n = max(2, count($vals));
        $ymax = self::niceMax(max([0, ...$vals]));
        $xy = [];
        foreach ($vals as $i => $v) {
            $xy[] = [$i / ($n - 1) * 1000, 300 - $v / $ymax * 300];
        }
        $line = self::smooth($xy);
        $ticks = self::ticks($ymax);
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [
                'v' => $v,
                'label' => (string) $labels[$i],
                'x' => number_format($i / ($n - 1) * 100, 2, '.', '').'%',
                'y' => number_format(100 - $v / $ymax * 100, 2, '.', '').'%',
                'delay' => self::delay($i, 0.4, 0.09),
            ];
        }
        $peakIdx = $vals ? array_keys($vals, max($vals))[0] : 0;

        return [
            'line' => $line,
            'area' => $line.' L1000,300 L0,300 Z',
            'ticks' => $ticks,
            'pts' => $pts,
            'peak' => $vals && max($vals) > 0 ? $pts[$peakIdx] : null,
            'empty' => ! $vals || max($vals) === 0,
        ];
    }

    /** Vertical columns: label => value. */
    public static function columns(array $series, string $color, string $peakColor, ?float $ymax = null): array
    {
        $vals = array_values($series);
        $ymax ??= self::niceMax(max([0, ...$vals]));
        $peak = max([0, ...$vals]);
        $out = [];
        $i = 0;
        foreach ($series as $label => $v) {
            $out[] = [
                'label' => (string) $label,
                'v' => $v,
                'has' => $v > 0,
                'h' => $v > 0 ? number_format($v / $ymax * 100, 1, '.', '').'%' : '3px',
                'c' => $v <= 0 ? '#E6EDEE' : ($v == $peak ? $peakColor : $color),
                'delay' => self::delay($i),
            ];
            $i++;
        }

        return ['cols' => $out, 'ticks' => self::ticks($ymax)];
    }

    /** Gridline values; picks 3, 2 or 4 steps so labels stay whole numbers where possible. */
    public static function ticks(float $ymax, ?int $steps = null): array
    {
        $steps ??= fmod($ymax, 3) == 0 ? 3 : (fmod($ymax, 2) == 0 ? 2 : 4);
        $out = [];
        for ($k = $steps; $k >= 0; $k--) {
            $v = $ymax * $k / $steps;
            $out[] = ['v' => self::fmt($v, fmod($v, 1) ? 1 : 0), 'top' => number_format(100 - $k / $steps * 100, 2, '.', '').'%'];
        }

        return $out;
    }

    /** Catmull-Rom → cubic Bézier, clamped so flat zero runs stay flat. */
    private static function smooth(array $p): string
    {
        if (! $p) {
            return 'M0,300 L1000,300';
        }
        $f = static fn ($v) => number_format($v, 1, '.', '');
        $d = 'M'.$f($p[0][0]).','.$f($p[0][1]);
        $n = count($p);
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $p[$i - 1] ?? $p[$i];
            $p1 = $p[$i];
            $p2 = $p[$i + 1];
            $p3 = $p[$i + 2] ?? $p2;
            $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
            $c1y = min(300, max(0, $p1[1] + ($p2[1] - $p0[1]) / 6));
            $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
            $c2y = min(300, max(0, $p2[1] - ($p3[1] - $p1[1]) / 6));
            if ($p1[1] == 300 && $p2[1] == 300) {
                $c1y = $c2y = 300;
            }
            $d .= ' C'.$f($c1x).','.$f($c1y).' '.$f($c2x).','.$f($c2y).' '.$f($p2[0]).','.$f($p2[1]);
        }

        return $d;
    }
}
