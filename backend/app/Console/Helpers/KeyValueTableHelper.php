<?php

namespace App\Console\Helpers;

class KeyValueTableHelper
{
    /**
     * @param array<string, scalar|null> $data
     * @return array<int, array{0:string,1:string}>
     */
    public static function fromAssoc(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $rows[] = [(string) $key, (string) ($value ?? '-')];
        }

        return $rows;
    }
}
