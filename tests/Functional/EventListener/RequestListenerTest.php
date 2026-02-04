<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\EventListener;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\EventListener\RequestListener;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class RequestListenerTest extends TestCase
{
    public function testRequestListenerIsRegisteredInContainer(): void
    {
        $kernel = new RequestListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(RequestListener::class));

        $listener = $container->get(RequestListener::class);
        $this->assertInstanceOf(RequestListener::class, $listener);

        $kernel->shutdown();
    }

    public function testRequestListenerGeneratesIdWhenNoHeader(): void
    {
        $kernel = new RequestListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $listener = $container->get(RequestListener::class);

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertTrue($storage->has());
        $this->assertNotNull($storage->get());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $storage->get()
        );

        $kernel->shutdown();
    }

    public function testRequestListenerUsesHeaderWhenPresent(): void
    {
        $kernel = new RequestListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $listener = $container->get(RequestListener::class);

        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', 'my-custom-id-123');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertTrue($storage->has());
        $this->assertSame('my-custom-id-123', $storage->get());

        $kernel->shutdown();
    }

    public function testRequestListenerGeneratesNewIdWhenHeaderInvalid(): void
    {
        $kernel = new RequestListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $listener = $container->get(RequestListener::class);

        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', '');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertTrue($storage->has());
        $this->assertNotNull($storage->get());
        $this->assertNotEmpty($storage->get());

        $kernel->shutdown();
    }
}

class RequestListenerTestKernel extends Kernel
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
