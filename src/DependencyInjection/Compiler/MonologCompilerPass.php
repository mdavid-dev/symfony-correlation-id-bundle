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

        // Ajouter le processor à tous les loggers Monolog
        $this->addProcessorToLoggers($container);
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

    private function addProcessorToLoggers(ContainerBuilder $container): void
    {
        // Chercher tous les services dont l'ID commence par "monolog.logger"
        foreach ($container->getDefinitions() as $id => $definition) {
            // Filtrer uniquement les vrais loggers Monolog
            if (!str_starts_with($id, 'monolog.logger')) {
                continue;
            }

            // Vérifier que c'est bien un logger Monolog
            $class = $definition->getClass();
            if ($class === null) {
                $class = $id;
            }

            // Résoudre la classe si c'est un paramètre
            if (str_starts_with($class, '%') && str_ends_with($class, '%')) {
                $class = $container->getParameter(trim($class, '%'));
            }

            // Vérifier que c'est bien Monolog\Logger ou une sous-classe
            if ($class !== 'Monolog\\Logger' && !is_subclass_of($class, 'Monolog\\Logger')) {
                continue;
            }

            // Ajouter le processor
            $definition->addMethodCall('pushProcessor', [
                new Reference(CorrelationIdProcessor::class)
            ]);
        }
    }
}