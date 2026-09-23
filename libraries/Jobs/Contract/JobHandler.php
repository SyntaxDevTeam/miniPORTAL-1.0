<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Contract;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Worker\JobExecution;

interface JobHandler
{
    public function handle(JobExecution $execution): void;
}
