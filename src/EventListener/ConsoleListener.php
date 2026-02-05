<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleListener implements EventSubscriberInterface
{
    public const OPTION_NAME = 'correlation-id';

    public function __construct(
        private readonly CorrelationIdStorage            $storage,
        private readonly CorrelationIdGeneratorInterface $generator,
        private readonly CorrelationIdValidator          $validator,
        private readonly string                          $prefix = 'CLI-',
        private readonly bool                            $allowOption = true
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 512],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -512],
            ConsoleEvents::ERROR => ['onConsoleError', -512],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command === null) {
            return;
        }

        $input = $event->getInput();
        $correlationId = null;

        if ($this->allowOption) {
            $value = $input->getParameterOption('--' . self::OPTION_NAME, null);
            if (is_string($value) && $value !== '') {
                $correlationId = $this->validator->sanitize($value);
            }
        }

        if ($correlationId === null) {
            $correlationId = $this->prefix . $this->generator->generate();
        }

        $this->storage->set($correlationId);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->storage->clear();
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->storage->clear();
    }
}
