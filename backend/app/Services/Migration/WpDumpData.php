<?php

namespace App\Services\Migration;

class WpDumpData
{
    /** @param  array<string, array<int, array<string, mixed>>>  $tables */
    /** @param  array<string, mixed>  $options */
    /** @param  array<int, array<string, mixed>>  $wpUsers */
    /** @param  array<int, array<string, mixed>>  $wpUsermeta */
    public function __construct(
        public array $tables = [],
        public array $options = [],
        public array $wpUsers = [],
        public array $wpUsermeta = [],
    ) {}

    /** @return array<string, int> */
    public function tableCounts(): array
    {
        $out = [];
        foreach ($this->tables as $table => $rows) {
            $out[$table] = count($rows);
        }

        return $out;
    }
}
