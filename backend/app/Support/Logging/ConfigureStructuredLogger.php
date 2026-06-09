<?php

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

class ConfigureStructuredLogger
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter());
        }
    }
}
