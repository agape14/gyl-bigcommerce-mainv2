<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class YourExportClass implements FromArray
{
    protected $records;

    public function __construct(array $records)
    {
        $this->records = $records;
    }

    public function array(): array
    {
        return $this->records;
    }
}
