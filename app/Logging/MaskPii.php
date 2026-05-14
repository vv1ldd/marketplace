<?php

namespace App\Logging;

use Monolog\Logger;

class MaskPii
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new PiiMaskingProcessor());
        }
    }
}
