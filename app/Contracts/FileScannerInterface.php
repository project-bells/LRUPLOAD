<?php

declare(strict_types=1);

namespace App\Contracts;

interface FileScannerInterface
{
    /**
     * Scan an uploaded file before committing it to private storage.
     * Returns one of: 'clean', 'infected', 'not_scanned'.
     */
    public function scan(string $absoluteFilePath): string;
}
