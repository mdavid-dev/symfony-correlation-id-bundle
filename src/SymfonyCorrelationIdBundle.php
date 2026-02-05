<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Compiler\MonologCompilerPass;
use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\CorrelationIdExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function dirname;

final class SymfonyCorrelationIdBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new CorrelationIdExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MonologCompilerPass());
    }
}
