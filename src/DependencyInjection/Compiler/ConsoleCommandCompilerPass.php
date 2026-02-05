<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler;

use MdavidDev\SymfonyCorrelationIdBundle\Console\ApplicationDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ConsoleCommandCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('correlation_id.cli.allow_option') || !$container->getParameter('correlation_id.cli.allow_option')) {
            return;
        }

        if (!$container->hasDefinition('console.application')) {
            return;
        }

        if (!$container->hasDefinition(ApplicationDecorator::class)) {
            $container->register(ApplicationDecorator::class, ApplicationDecorator::class)
                ->setDecoratedService('console.application')
                ->setArguments([new Reference('.inner')])
                ->addMethodCall('setDispatcher', [new Reference('event_dispatcher')])
                ->setPublic(false);
        }
    }
}
