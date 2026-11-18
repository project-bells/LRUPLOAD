<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Contracts\FileScannerInterface;

/**
 * Default pass-through file scanner placeholder.
 * Swap binding in AppServiceProvider when integrating ClamAV or hospital AV engine.
 */
final class NullFileScanner implements FileScannerInterface
{
    public function scan(string $absoluteFilePath): string
    {
        return 'not_scanned';
    }
}
