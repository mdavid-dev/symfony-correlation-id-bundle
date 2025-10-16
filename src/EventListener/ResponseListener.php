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
        private readonly string $headerName
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Ne traiter que la requête principale (pas les sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        // Si pas d'ID de corrélation, on ne fait rien
        if (!$this->storage->has()) {
            return;
        }

        $correlationId = $this->storage->get();
        $response = $event->getResponse();

        // Ajouter l'ID dans le header de réponse
        $response->headers->set($this->headerName, $correlationId);
    }
}