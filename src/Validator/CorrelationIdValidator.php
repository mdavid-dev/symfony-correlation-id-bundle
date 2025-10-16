<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Validator;

final class CorrelationIdValidator
{
    public function __construct(
        private readonly bool $enabled,
        private readonly int $maxLength,
        private readonly ?string $pattern
    ) {
    }

    /**
     * Validate if a correlation ID is valid.
     */
    public function isValid(?string $correlationId): bool
    {
        // Si la validation est désactivée, tout est valide
        if (!$this->enabled) {
            return true;
        }

        // Null ou chaîne vide = invalide
        if ($correlationId === null || $correlationId === '') {
            return false;
        }

        // Vérifier la longueur max
        if (mb_strlen($correlationId) > $this->maxLength) {
            return false;
        }

        // Vérifier le pattern si défini
        if ($this->pattern !== null) {
            return preg_match($this->pattern, $correlationId) === 1;
        }

        return true;
    }

    /**
     * Validate and sanitize a correlation ID.
     * Returns the sanitized ID if valid, null otherwise.
     */
    public function sanitize(?string $correlationId): ?string
    {
        // Trim d'abord (si pas null)
        if ($correlationId !== null) {
            $correlationId = trim($correlationId);
        }

        // Puis valider
        if (!$this->isValid($correlationId)) {
            return null;
        }

        return $correlationId;
    }
}