<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Sources\SourceAlerts;
use App\Sources\SourceName;
use Illuminate\Console\Command;

class CheckSourceSilenceCommand extends Command
{
    protected $signature = 'sources:check-silence';

    protected $description = 'Alert the group about sources that brought no new listings for a day';

    public function handle(SourceAlerts $alerts): int
    {
        $now = now()->toImmutable();

        foreach (SourceName::cases() as $name) {
            if ($alerts->checkSilence(Source::for($name), $now)) {
                $this->warn("{$name->value}: no new listings, alert sent");
            }
        }

        return self::SUCCESS;
    }
}
