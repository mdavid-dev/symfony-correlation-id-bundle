<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Service\Generator;

use Symfony\Component\Uid\Uuid;

final class UuidV4Generator implements CorrelationIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}