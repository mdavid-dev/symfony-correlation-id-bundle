<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Monolog;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class MonologIntegrationTest extends TestCase
{
    public function testProcessorIsRegisteredWhenEnabled(): void
    {
        $kernel = new MonologTestKernel('test', true, true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(CorrelationIdProcessor::class));

        $processor = $container->get(CorrelationIdProcessor::class);
        $this->assertInstanceOf(CorrelationIdProcessor::class, $processor);

        $kernel->shutdown();
    }

    public function testProcessorIsNotRegisteredWhenDisabled(): void
    {
        $kernel = new MonologTestKernel('test', true, false);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertFalse($container->has(CorrelationIdProcessor::class));

        $kernel->shutdown();
    }

    public function testProcessorUsesConfiguredKey(): void
    {
        $kernel = new MonologTestKernelCustomKey('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(CorrelationIdProcessor::class));

        $kernel->shutdown();
    }
}

class MonologTestKernel extends Kernel
{
    public function __construct(
        string $environment,
        bool $debug,
        private readonly bool $monologEnabled
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SymfonyCorrelationIdBundle(),
        ];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret',
                'test' => true,
            ]);

            $container->loadFromExtension('correlation_id', [
                'monolog' => [
                    'enabled' => $this->monologEnabled,
                ],
            ]);
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $definition->setPublic(true);
                    }
                }

                foreach ($container->getAliases() as $id => $alias) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $alias->setPublic(true);
                    }
                }
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/logs';
    }
}

class MonologTestKernelCustomKey extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SymfonyCorrelationIdBundle(),
        ];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret',
                'test' => true,
            ]);

            $container->loadFromExtension('correlation_id', [
                'monolog' => [
                    'enabled' => true,
                    'key' => 'custom_request_id',
                ],
            ]);
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $definition->setPublic(true);
                    }
                }

                foreach ($container->getAliases() as $id => $alias) {
                    if (str_starts_with($id, 'MdavidDev\\SymfonyCorrelationIdBundle\\')) {
                        $alias->setPublic(true);
                    }
                }
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/symfony-correlation-id-bundle/logs';
    }
}