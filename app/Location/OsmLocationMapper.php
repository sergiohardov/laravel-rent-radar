<?php

namespace App\Location;

/**
 * OpenStreetMap elements around the sector point → whitelist entries with aliases.
 */
final class OsmLocationMapper
{
    // Not \b: without Unicode properties PCRE does not count Cyrillic as word characters
    private const COMPLEX_NAME = '/^(ЖК|ЖМ|Житловий комплекс|Жилой комплекс)(\s|$)/u';

    /** Planning names nobody writes in a listing */
    private const SKIPPED_NAME = '/^Мікрорайон(\s|$)/u';

    /** Tags that hold other names of the same place */
    private const ALIAS_TAGS = ['name:uk', 'name:ru', 'old_name', 'old_name:uk', 'old_name:ru', 'alt_name', 'alt_name:uk', 'alt_name:ru', 'official_name', 'short_name'];

    /**
     * @param  list<array<string, mixed>>  $elements  Overpass "out tags" elements
     * @return list<array{kind: LocationKind, name: string, aliases: list<string>}>
     */
    public function map(array $elements): array
    {
        $entries = [];

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            $name = trim((string) ($tags['name'] ?? ''));
            $kind = $this->kind($tags, $name);

            if ($name === '' || $kind === null || preg_match(self::SKIPPED_NAME, $name)) {
                continue;
            }

            $key = $kind->value.'|'.$name;
            $entries[$key] = [
                'kind' => $kind,
                'name' => $name,
                'aliases' => $this->aliases($tags, $name, $entries[$key]['aliases'] ?? []),
            ];
        }

        return array_values($entries);
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function kind(array $tags, string $name): ?LocationKind
    {
        return match (true) {
            isset($tags['highway']) => LocationKind::Street,
            in_array($tags['place'] ?? null, ['suburb', 'quarter'], true) => LocationKind::District,
            ($tags['building'] ?? null) === 'apartments' => LocationKind::Complex,
            (isset($tags['landuse']) || isset($tags['place'])) && preg_match(self::COMPLEX_NAME, $name) === 1 => LocationKind::Complex,
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $tags
     * @param  list<string>  $known
     * @return list<string>
     */
    private function aliases(array $tags, string $name, array $known): array
    {
        $aliases = $known;
        foreach (self::ALIAS_TAGS as $tag) {
            foreach (explode(';', (string) ($tags[$tag] ?? '')) as $alias) {
                $aliases[] = trim($alias);
            }
        }

        return array_values(array_unique(array_filter($aliases, fn (string $alias) => $alias !== '' && $alias !== $name)));
    }
}
