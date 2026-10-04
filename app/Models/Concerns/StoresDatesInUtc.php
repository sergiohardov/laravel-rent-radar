<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * MariaDB DATETIME keeps no time zone and rejects a UTC offset, so every date
 * is converted to UTC before it is written: a publishing time a source gives
 * in Kyiv time (DIM.RIA, OLX) is stored as the same moment in UTC.
 */
trait StoresDatesInUtc
{
    /**
     * @param  DateTimeInterface|string|int|null  $value
     */
    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->setTimezone('UTC')->format($this->getDateFormat());
    }
}
