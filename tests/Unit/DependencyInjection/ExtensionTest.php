<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\DependencyInjection;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\CorrelationIdExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExtensionTest extends TestCase
{
    public function testExtensionLoadsWithDefaultConfig(): void
    {
        $container = new ContainerBuilder();
        $extension = new CorrelationIdExtension();

        $extension->load([], $container);

        $this->assertTrue($container->hasParameter('correlation_id.header_name'));
        $this->assertSame('X-Correlation-ID', $container->getParameter('correlation_id.header_name'));

        $this->assertTrue($container->hasParameter('correlation_id.generator'));
        $this->assertSame('uuid_v4', $container->getParameter('correlation_id.generator'));

        $this->assertTrue($container->hasParameter('correlation_id.trust_header'));
        $this->assertTrue($container->getParameter('correlation_id.trust_header'));
    }

    public function testExtensionLoadsWithCustomConfig(): void
    {
        $container = new ContainerBuilder();
        $extension = new CorrelationIdExtension();

        $config = [
            'correlation_id' => [
                'header_name' => 'X-Custom-ID',
                'generator' => 'ulid',
                'trust_header' => false,
            ],
        ];

        $extension->load($config, $container);

        $this->assertSame('X-Custom-ID', $container->getParameter('correlation_id.header_name'));
        $this->assertSame('ulid', $container->getParameter('correlation_id.generator'));
        $this->assertFalse($container->getParameter('correlation_id.trust_header'));
    }

    public function testExtensionAlias(): void
    {
        $extension = new CorrelationIdExtension();

        $this->assertSame('correlation_id', $extension->getAlias());
    }
}