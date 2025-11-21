<?php

namespace Dniccum\Vaultr\Commands;

use Illuminate\Console\Command;

class VaultrCommand extends Command
{
    public $signature = 'vaultr-cli';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
