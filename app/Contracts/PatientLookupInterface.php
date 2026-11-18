<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PatientData;

interface PatientLookupInterface
{
    /**
     * Lookup a patient by normalized HN from the read-only HIS source (SQL Server 2014).
     *
     * @throws \App\Exceptions\PatientLookupException
     */
    public function findByHn(string $hn): ?PatientData;
}
