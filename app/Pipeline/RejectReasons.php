<?php

namespace App\Pipeline;

use Illuminate\Database\Eloquent\Builder;

/**
 * Reject reasons grouped by what failed, for the journal filter and the
 * dashboard. The patterns follow the reason texts the stages write.
 */
final class RejectReasons
{
    /**
     * @var array<string, array{label: string, patterns: list<string>}>
     */
    public const GROUPS = [
        'rooms' => ['label' => 'Комнаты', 'patterns' => ['rooms %']],
        'floor' => ['label' => 'Этаж', 'patterns' => ['floor %']],
        'price' => ['label' => 'Цена', 'patterns' => ['price %']],
        'generator' => ['label' => 'Генератор', 'patterns' => ['no generator%', 'generator unknown%']],
        'outages' => ['label' => 'Вода или тепло при отключениях', 'patterns' => ['no water%', 'no heating%']],
        'sector' => ['label' => 'Вне сектора', 'patterns' => ['not in sector%']],
    ];

    /**
     * @return array<string, string> group key => label
     */
    public static function labels(): array
    {
        return array_map(fn (array $group) => $group['label'], self::GROUPS);
    }

    /**
     * Narrows a listings query to one group; an unknown key leaves it as is.
     */
    public static function apply(Builder $query, string $group): Builder
    {
        $patterns = self::GROUPS[$group]['patterns'] ?? null;
        if ($patterns === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($patterns) {
            foreach ($patterns as $pattern) {
                $query->orWhere('reject_reason', 'like', $pattern);
            }
        });
    }
}
