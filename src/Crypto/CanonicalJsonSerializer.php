<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Crypto;

final class CanonicalJsonSerializer
{
    /**
     * Serialize data to canonical JSON.
     *
     * Rules:
     * - Recursively sort object keys lexicographically
     * - Preserve array element order (arrays are NOT sorted)
     * - Compact output (no whitespace)
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \JsonException
     */
    public function serialize(array $data): string
    {
        $sorted = $this->recursiveKeySort($data);

        return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function recursiveKeySort(array $data): array
    {
        // Only sort associative arrays (objects), preserve sequential array order
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveKeySort($value);
            }
        }

        return $data;
    }
}
