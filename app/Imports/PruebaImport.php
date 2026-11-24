<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class PruebaImport implements ToArray
{
    public function array(array $array): void
    {
        // Las filas se obtienen mediante Excel::toArray.
    }
}
