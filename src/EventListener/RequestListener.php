<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CorrelationIdStorage            $storage,
        private readonly CorrelationIdGeneratorInterface $generator,
        private readonly CorrelationIdValidator          $validator,
        private readonly string                          $headerName,
        private readonly bool                            $trustHeader
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $correlationId = null;

        if ($this->storage->has()) {
            return;
        }

        if ($this->trustHeader && $request->headers->has($this->headerName)) {
            $headerValue = $request->headers->get($this->headerName);
            $sanitizedId = $this->validator->sanitize($headerValue);

            if ($sanitizedId !== null) {
                $correlationId = $sanitizedId;
            }
        }

        if ($correlationId === null) {
            $correlationId = $this->generator->generate();
        }

        $this->storage->set($correlationId);
    }
}
