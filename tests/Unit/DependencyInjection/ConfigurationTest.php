<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\DependencyInjection;

use MdavidDev\SymfonyCorrelationIdBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame('X-Correlation-ID', $config['header_name']);
        $this->assertSame('uuid_v4', $config['generator']);
        $this->assertTrue($config['trust_header']);

        $this->assertTrue($config['validation']['enabled']);
        $this->assertSame(255, $config['validation']['max_length']);
        $this->assertNull($config['validation']['pattern']);

        $this->assertTrue($config['monolog']['enabled']);
        $this->assertSame('correlation_id', $config['monolog']['key']);

        $this->assertTrue($config['http_client']['enabled']);
        $this->assertTrue($config['messenger']['enabled']);

        $this->assertTrue($config['cli']['enabled']);
        $this->assertSame('CLI-', $config['cli']['prefix']);
        $this->assertTrue($config['cli']['allow_option']);
    }

    public function testCustomConfiguration(): void
    {
        $customConfig = [
            'correlation_id' => [
                'header_name' => 'X-Request-ID',
                'generator' => 'ulid',
                'trust_header' => false,
                'validation' => [
                    'enabled' => false,
                    'max_length' => 128,
                    'pattern' => '^[a-z0-9-]+$',
                ],
                'monolog' => [
                    'enabled' => false,
                    'key' => 'request_id',
                ],
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);

        $this->assertSame('X-Request-ID', $config['header_name']);
        $this->assertSame('ulid', $config['generator']);
        $this->assertFalse($config['trust_header']);

        $this->assertFalse($config['validation']['enabled']);
        $this->assertSame(128, $config['validation']['max_length']);
        $this->assertSame('^[a-z0-9-]+$', $config['validation']['pattern']);

        $this->assertFalse($config['monolog']['enabled']);
        $this->assertSame('request_id', $config['monolog']['key']);
    }

    public function testPartialConfiguration(): void
    {
        $partialConfig = [
            'correlation_id' => [
                'header_name' => 'X-Trace-ID',
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $partialConfig);

        // Vérifie que header_name est personnalisé
        $this->assertSame('X-Trace-ID', $config['header_name']);

        // Vérifie que les autres valeurs sont les valeurs par défaut
        $this->assertSame('uuid_v4', $config['generator']);
        $this->assertTrue($config['trust_header']);
    }
}