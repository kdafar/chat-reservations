<?php

namespace App\Wa\Traits;

use Psr\Log\LoggerInterface;

trait LogCustomize
{
    public static function setMaxLoggerNormalizeDepth(LoggerInterface $logger, int $depth): void
    {
        $handlers = $logger->getHandlers();
        foreach ($handlers as $handler) {
            $formatter = $handler->getFormatter();

            if (method_exists($formatter, 'setMaxNormalizeDepth')) {
                $formatter->setMaxNormalizeDepth($depth);
            } elseif (method_exists($formatter, 'setMaxDepth')) {
                // older versions of Monolog
                $formatter->setMaxDepth($depth);
            }
        }
    }
}
