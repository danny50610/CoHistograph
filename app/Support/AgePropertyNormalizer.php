<?php

namespace App\Support;

/**
 * Normalizes AGE vertex/edge property maps after they are read from the driver.
 *
 * The Apache AGE Laravel driver runs addslashes() on string values before putting
 * them in Cypher parameters (JSON/agtype). Apostrophes therefore come back as \'.
 *
 * Only that quote escape is undone. stripslashes() is intentionally not used:
 * it would also collapse legitimate backslashes (paths, regex, escaped newlines).
 */
class AgePropertyNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $properties): array
    {
        if (is_object($properties)) {
            $properties = (array) $properties;
        }

        if (! is_array($properties)) {
            return [];
        }

        /** @var array<string, mixed> $unescaped */
        $unescaped = $this->unescapeRecursive($properties);

        return $unescaped;
    }

    private function unescapeRecursive(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->unescapeAgeQuotes($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[$key] = $this->unescapeRecursive($item);
        }

        return $result;
    }

    /**
     * Undo addslashes() escaping of ' and " only.
     *
     * Double quotes are included because addslashes() also prefixes them with a
     * backslash; leaving \" visible would be the same class of display bug.
     */
    private function unescapeAgeQuotes(string $value): string
    {
        return str_replace(['\\\'', '\\"'], ["'", '"'], $value);
    }
}
