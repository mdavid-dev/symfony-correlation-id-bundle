<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\DependencyInjection;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\CorrelationIdExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CorrelationIdExtensionTest extends TestCase
{
    public function testGetAlias(): void
    {
        $extension = new CorrelationIdExtension();
        $this->assertSame('correlation_id', $extension->getAlias());
    }

    public function testLoadRemovesConsoleListenerWhenCliDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new CorrelationIdExtension();

        $configs = [
            'correlation_id' => [
                'cli' => [
                    'enabled' => false
                ]
            ]
        ];

        $extension->load($configs, $container);

        $this->assertFalse($container->hasDefinition('MdavidDev\SymfonyCorrelationIdBundle\EventListener\ConsoleListener'));
    }
}
