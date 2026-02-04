<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class CorrelationIdStorage
{
    private const ATTRIBUTE_NAME = '_correlation_id';

    private ?string $fallbackId = null;

    public function __construct(
        private readonly RequestStack $requestStack
    )
    {
    }

    /**
     * Get the current correlation ID.
     */
    public function get(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            return $request->attributes->get(self::ATTRIBUTE_NAME);
        }

        return $this->fallbackId;
    }

    /**
     * Set the correlation ID.
     */
    public function set(string $correlationId): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            $request->attributes->set(self::ATTRIBUTE_NAME, $correlationId);
        } else {
            $this->fallbackId = $correlationId;
        }
    }

    /**
     * Check if a correlation ID exists.
     */
    public function has(): bool
    {
        return $this->get() !== null;
    }

    /**
     * Clear the correlation ID.
     */
    public function clear(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $request?->attributes->remove(self::ATTRIBUTE_NAME);

        $this->fallbackId = null;
    }
}
