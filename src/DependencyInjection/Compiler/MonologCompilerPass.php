<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler;

use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MonologCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Vérifier si Monolog est disponible
        if (!$this->isMonologAvailable()) {
            return;
        }

        // Vérifier si l'intégration Monolog est activée
        if (!$container->hasParameter('correlation_id.monolog')) {
            return;
        }

        $monologConfig = $container->getParameter('correlation_id.monolog');

        if (!is_array($monologConfig) || !($monologConfig['enabled'] ?? false)) {
            return;
        }

        // Enregistrer le processor
        $this->registerProcessor($container, $monologConfig);
    }

    protected function isMonologAvailable(): bool
    {
        return class_exists('Monolog\Logger');
    }

    private function registerProcessor(ContainerBuilder $container, array $monologConfig): void
    {
        $processorDefinition = $container->register(
            CorrelationIdProcessor::class,
            CorrelationIdProcessor::class
        );

        $processorDefinition
            ->setArguments([
                new Reference('MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage'),
                $monologConfig['key'] ?? 'correlation_id',
            ])
            ->addTag('monolog.processor')
            ->setPublic(false);
    }
}