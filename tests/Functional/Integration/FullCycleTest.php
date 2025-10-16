<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Functional\Integration;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\EventListener\RequestListener;
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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class FullCycleTest extends TestCase
{
    public function testFullCycleWithoutIncomingHeader(): void
    {
        $kernel = new FullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer une requête SANS header
        $request = Request::create('/test');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        // 2. Traiter la requête
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        // 3. Vérifier qu'un ID a été généré
        $this->assertTrue($storage->has());
        $generatedId = $storage->get();
        $this->assertNotNull($generatedId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $generatedId
        );

        // 4. Créer et traiter la réponse
        $response = new Response('OK', 200);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $responseListener->onKernelResponse($responseEvent);

        // 5. Vérifier que l'ID est dans le header de réponse
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame($generatedId, $response->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testFullCycleWithIncomingHeader(): void
    {
        $kernel = new FullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer une requête AVEC header
        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', 'incoming-id-from-client');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        // 2. Traiter la requête
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        // 3. Vérifier que l'ID du header a été utilisé
        $this->assertTrue($storage->has());
        $this->assertSame('incoming-id-from-client', $storage->get());

        // 4. Créer et traiter la réponse
        $response = new Response('OK', 200);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $responseListener->onKernelResponse($responseEvent);

        // 5. Vérifier que le même ID est dans le header de réponse
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame('incoming-id-from-client', $response->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testFullCycleWithInvalidIncomingHeader(): void
    {
        $kernel = new FullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer une requête avec header INVALIDE
        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', ''); // Vide = invalide
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        // 2. Traiter la requête
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        // 3. Vérifier qu'un nouvel ID a été généré (pas le header invalide)
        $this->assertTrue($storage->has());
        $generatedId = $storage->get();
        $this->assertNotNull($generatedId);
        $this->assertNotEmpty($generatedId);
        $this->assertNotSame('', $generatedId);

        // 4. Créer et traiter la réponse
        $response = new Response('OK', 200);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $responseListener->onKernelResponse($responseEvent);

        // 5. Vérifier que l'ID généré est dans le header de réponse
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame($generatedId, $response->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testFullCycleWithTrustHeaderDisabled(): void
    {
        $kernel = new FullCycleTestKernelNoTrust('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer une requête avec header
        $request = Request::create('/test');
        $request->headers->set('X-Correlation-ID', 'header-should-be-ignored');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        // 2. Traiter la requête
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        // 3. Vérifier qu'un nouvel ID a été généré (header ignoré)
        $this->assertTrue($storage->has());
        $generatedId = $storage->get();
        $this->assertNotSame('header-should-be-ignored', $generatedId);

        // 4. Créer et traiter la réponse
        $response = new Response('OK', 200);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $responseListener->onKernelResponse($responseEvent);

        // 5. Vérifier que l'ID généré est dans le header de réponse
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame($generatedId, $response->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testFullCycleWithCustomHeaderName(): void
    {
        $kernel = new FullCycleTestKernelCustomHeader('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer une requête avec header custom
        $request = Request::create('/test');
        $request->headers->set('X-Request-ID', 'custom-header-id');
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        // 2. Traiter la requête
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($requestEvent);

        // 3. Vérifier que l'ID du header custom a été utilisé
        $this->assertTrue($storage->has());
        $this->assertSame('custom-header-id', $storage->get());

        // 4. Créer et traiter la réponse
        $response = new Response('OK', 200);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $responseListener->onKernelResponse($responseEvent);

        // 5. Vérifier que le header custom est dans la réponse
        $this->assertTrue($response->headers->has('X-Request-ID'));
        $this->assertSame('custom-header-id', $response->headers->get('X-Request-ID'));
        $this->assertFalse($response->headers->has('X-Correlation-ID'));

        $kernel->shutdown();
    }

    public function testSubRequestsKeepSameId(): void
    {
        $kernel = new FullCycleTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $storage = $container->get(CorrelationIdStorage::class);
        $requestListener = $container->get(RequestListener::class);
        $responseListener = $container->get(ResponseListener::class);

        // 1. Créer et traiter la requête PRINCIPALE
        $mainRequest = Request::create('/main');
        $requestStack = $container->get('request_stack');
        $requestStack->push($mainRequest);

        $mainRequestEvent = new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST);
        $requestListener->onKernelRequest($mainRequestEvent);

        $mainId = $storage->get();
        $this->assertNotNull($mainId);

        // 2. Créer une SUB-REQUEST
        $subRequest = Request::create('/sub');
        $requestStack->push($subRequest);

        // 3. Le listener doit IGNORER les sub-requests
        $subRequestEvent = new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST);
        $requestListener->onKernelRequest($subRequestEvent);

        // 4. Le storage cherche dans la requête courante (sub-request)
        // qui n'a pas l'ID, donc retourne null
        $this->assertNull($storage->get());

        // 5. Traiter la réponse de la sub-request
        $subResponse = new Response('SUB OK', 200);
        $subResponseEvent = new ResponseEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST, $subResponse);
        $responseListener->onKernelResponse($subResponseEvent);

        // 6. La sub-response ne doit PAS avoir le header (sub-request ignorée)
        $this->assertFalse($subResponse->headers->has('X-Correlation-ID'));

        // 7. Revenir à la requête principale
        $requestStack->pop(); // Retirer sub-request

        // 8. Maintenant on retrouve l'ID de la requête principale
        $this->assertSame($mainId, $storage->get());

        // 9. Traiter la réponse principale
        $mainResponse = new Response('MAIN OK', 200);
        $mainResponseEvent = new ResponseEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST, $mainResponse);
        $responseListener->onKernelResponse($mainResponseEvent);

        // 10. La main response doit avoir le header
        $this->assertTrue($mainResponse->headers->has('X-Correlation-ID'));
        $this->assertSame($mainId, $mainResponse->headers->get('X-Correlation-ID'));

        $kernel->shutdown();
    }
}

// Kernel par défaut
class FullCycleTestKernel extends Kernel
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

// Kernel avec trust_header = false
class FullCycleTestKernelNoTrust extends Kernel
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
                'trust_header' => false,
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

// Kernel avec header custom
class FullCycleTestKernelCustomHeader extends Kernel
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
                'header_name' => 'X-Request-ID',
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