<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\EventListener;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\EventListener\ResponseListener;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class ResponseListenerTest extends TestCase
{
    public function testResponseListenerIsRegisteredInContainer(): void
    {
        $kernel = new ResponseListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(ResponseListener::class));

        $listener = $container->get(ResponseListener::class);
        $this->assertInstanceOf(ResponseListener::class, $listener);

        $kernel->shutdown();
    }

    public function testResponseListenerAddsHeaderToResponse(): void
    {
        $kernel = new ResponseListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $listener = $container->get(ResponseListener::class);

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);
        $storage->set('functional-test-id');

        $response = new Response('OK', 200);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame('functional-test-id', $response->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testResponseListenerDoesNotAddHeaderWhenNoId(): void
    {
        $kernel = new ResponseListenerTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $listener = $container->get(ResponseListener::class);

        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $response = new Response('OK', 200);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-Correlation-ID'));

        $kernel->shutdown();
    }
}

class ResponseListenerTestKernel extends Kernel
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
