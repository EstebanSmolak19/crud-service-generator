<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    public $signature = 'crud-service-generator';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
