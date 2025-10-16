<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Service\Generator;

interface CorrelationIdGeneratorInterface
{
    /**
     * Generate a unique correlation ID.
     */
    public function generate(): string;
}