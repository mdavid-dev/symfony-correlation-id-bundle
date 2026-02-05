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
        if (!$this->isMonologAvailable()) {
            return;
        }

        if (!$container->hasParameter('correlation_id.monolog')) {
            return;
        }

        $monologConfig = $container->getParameter('correlation_id.monolog');

        if (!is_array($monologConfig) || !($monologConfig['enabled'] ?? false)) {
            return;
        }

        $this->registerProcessor($container, $monologConfig);

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
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'monolog.logger')) {
                continue;
            }

            $class = $definition->getClass();
            if ($class === null) {
                $class = $id;
            }

            if (str_starts_with($class, '%') && str_ends_with($class, '%')) {
                $class = $container->getParameter(trim($class, '%'));
            }

            if ($class !== 'Monolog\\Logger' && !is_subclass_of($class, 'Monolog\\Logger')) {
                continue;
            }

            $definition->addMethodCall('pushProcessor', [
                new Reference(CorrelationIdProcessor::class)
            ]);
        }
    }
}
