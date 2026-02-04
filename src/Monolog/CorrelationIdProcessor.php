<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Monolog;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorrelationIdStorage $storage,
        private readonly string               $key
    )
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (!$this->storage->has()) {
            return $record;
        }

        $correlationId = $this->storage->get();

        $record->extra[$this->key] = $correlationId;

        return $record;
    }
}
