<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Integration;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\EventListener\RequestListener;
use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class MonologFullCycleTest extends TestCase
{
    public function testCorrelationIdAppearsInLogs(): void
    {
        $kernel = new MonologFullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $logger = $container->get('test.logger');
        $testHandler = $container->get('test.handler');

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        $correlationId = $storage->get();
        $this->assertNotNull($correlationId);

        $logger->info('Test log message', ['user_id' => 123]);

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertSame('Test log message', $record->message);
        $this->assertArrayHasKey('correlation_id', $record->extra);
        $this->assertSame($correlationId, $record->extra['correlation_id']);
        $this->assertArrayHasKey('user_id', $record->context);
        $this->assertSame(123, $record->context['user_id']);

        $kernel->shutdown();
    }

    public function testCorrelationIdAppearsWithCustomKey(): void
    {
        $kernel = new MonologFullCycleTestKernelCustomKey('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $logger = $container->get('test.logger');
        $testHandler = $container->get('test.handler');

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        $correlationId = $storage->get();
        $this->assertNotNull($correlationId);

        $logger->warning('Custom key test');

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertArrayHasKey('request_id', $record->extra);
        $this->assertSame($correlationId, $record->extra['request_id']);
        $this->assertArrayNotHasKey('correlation_id', $record->extra);

        $kernel->shutdown();
    }

    public function testLogsWithoutCorrelationIdWhenNoRequest(): void
    {
        $kernel = new MonologFullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $logger = $container->get('test.logger');
        $testHandler = $container->get('test.handler');

        $logger->error('Error without correlation ID');

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertSame('Error without correlation ID', $record->message);
        $this->assertArrayNotHasKey('correlation_id', $record->extra);

        $kernel->shutdown();
    }

    public function testMultipleLogsHaveSameCorrelationId(): void
    {
        $kernel = new MonologFullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $logger = $container->get('test.logger');
        $testHandler = $container->get('test.handler');

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        $correlationId = $storage->get();

        $logger->debug('First log');
        $logger->info('Second log');
        $logger->warning('Third log');
        $logger->error('Fourth log');

        $records = $testHandler->getRecords();
        $this->assertCount(4, $records);

        foreach ($records as $record) {
            $this->assertArrayHasKey('correlation_id', $record->extra);
            $this->assertSame($correlationId, $record->extra['correlation_id']);
        }

        $kernel->shutdown();
    }

    public function testCorrelationIdWithIncomingHeader(): void
    {
        $kernel = new MonologFullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $requestListener = $container->get(RequestListener::class);
        $logger = $container->get('test.logger');
        $testHandler = $container->get('test.handler');

        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', 'incoming-correlation-123');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        $logger->info('Log with incoming ID');

        $records = $testHandler->getRecords();
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertArrayHasKey('correlation_id', $record->extra);
        $this->assertSame('incoming-correlation-123', $record->extra['correlation_id']);

        $kernel->shutdown();
    }
}

// Test kernel with Monolog enabled
class MonologFullCycleTestKernel extends Kernel
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
                    'key' => 'correlation_id',
                ],
            ]);

            $container->register('test.handler', TestHandler::class)
                ->addArgument(Level::Debug)
                ->setPublic(true);

            $container->register('test.logger', Logger::class)
                ->addArgument('test')
                ->addMethodCall('pushHandler', [new Reference('test.handler')])
                ->addMethodCall('pushProcessor', [new Reference(CorrelationIdProcessor::class)])
                ->setPublic(true);

            if ($container->hasAlias('request_stack')) {
                $container->getAlias('request_stack')->setPublic(true);
            }
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

// Kernel with custom key
class MonologFullCycleTestKernelCustomKey extends Kernel
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
                    'key' => 'request_id',
                ],
            ]);

            $container->register('test.handler', TestHandler::class)
                ->addArgument(Level::Debug)
                ->setPublic(true);

            $container->register('test.logger', Logger::class)
                ->addArgument('test')
                ->addMethodCall('pushHandler', [new Reference('test.handler')])
                ->addMethodCall('pushProcessor', [new Reference(CorrelationIdProcessor::class)])
                ->setPublic(true);

            if ($container->hasAlias('request_stack')) {
                $container->getAlias('request_stack')->setPublic(true);
            }
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
