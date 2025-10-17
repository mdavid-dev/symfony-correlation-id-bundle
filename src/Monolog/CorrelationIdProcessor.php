<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Monolog;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(
        private CorrelationIdStorage $storage,
        private string               $key
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Si pas d'ID, on ne fait rien
        if (!$this->storage->has()) {
            return $record;
        }

        $correlationId = $this->storage->get();

        // Ajouter l'ID dans le contexte du log
        $record->extra[$this->key] = $correlationId;

        return $record;
    }
}