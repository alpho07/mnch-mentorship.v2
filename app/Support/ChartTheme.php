<?php

namespace App\Support;

class ChartTheme
{
    public const PALETTE = ['#0097A7', '#F59E0B', '#10B981', '#8B5CF6', '#1C3A8A', '#DC2626'];

    public static function base(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 600,
                'easing' => 'easeOutQuart',
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
                'line' => [
                    'tension' => 0.35,
                ],
                'point' => [
                    'radius' => 3,
                    'hoverRadius' => 5,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'color' => 'rgba(15, 23, 42, 0.06)',
                        'drawBorder' => false,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'color' => 'rgba(15, 23, 42, 0.06)',
                        'drawBorder' => false,
                    ],
                ],
            ],
        ];
    }

    public static function merge(array $overrides): array
    {
        return self::deepMerge(self::base(), $overrides);
    }

    private static function deepMerge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && array_is_list($value) === false) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
