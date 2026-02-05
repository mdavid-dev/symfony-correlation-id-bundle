<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ResponseListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CorrelationIdStorage $storage,
        private readonly string               $headerName
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->storage->has()) {
            return;
        }

        $correlationId = $this->storage->get();
        $response = $event->getResponse();

        $response->headers->set($this->headerName, $correlationId);
    }
}
