<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestListener implements EventSubscriberInterface
{
    public function __construct(
        private CorrelationIdStorage            $storage,
        private CorrelationIdGeneratorInterface $generator,
        private CorrelationIdValidator          $validator,
        private string                          $headerName,
        private bool                            $trustHeader
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512], // Priorité haute
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ne traiter que la requête principale (pas les sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $correlationId = null;

        // Vérifier si un ID existe déjà dans le storage (peut arriver dans certains cas)
        if ($this->storage->has()) {
            return;
        }

        // Essayer de récupérer l'ID depuis le header
        if ($this->trustHeader && $request->headers->has($this->headerName)) {
            $headerValue = $request->headers->get($this->headerName);
            $sanitizedId = $this->validator->sanitize($headerValue);

            if ($sanitizedId !== null) {
                $correlationId = $sanitizedId;
            }
        }

        // Si pas d'ID valide, en générer un nouveau
        if ($correlationId === null) {
            $correlationId = $this->generator->generate();
        }

        // Stocker l'ID
        $this->storage->set($correlationId);
    }
}