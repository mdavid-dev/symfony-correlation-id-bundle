<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Validator;

final class CorrelationIdValidator
{
    public function __construct(
        private readonly bool    $enabled,
        private readonly int     $maxLength,
        private readonly ?string $pattern
    )
    {
    }

    public function isValid(?string $correlationId): bool
    {
        if (!$this->enabled) {
            return true;
        }

        if ($correlationId === null || $correlationId === '') {
            return false;
        }

        if (mb_strlen($correlationId) > $this->maxLength) {
            return false;
        }

        if ($this->pattern !== null) {
            return preg_match($this->pattern, $correlationId) === 1;
        }

        return true;
    }

    public function sanitize(?string $correlationId): ?string
    {
        if ($correlationId !== null) {
            $correlationId = trim($correlationId);
        }

        if (!$this->isValid($correlationId)) {
            return null;
        }

        return $correlationId;
    }
}
