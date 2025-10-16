<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\EventListener\ResponseListener;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseListenerTest extends TestCase
{
    private RequestStack $requestStack;
    private CorrelationIdStorage $storage;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->storage = new CorrelationIdStorage($this->requestStack);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testSubscribedEvents(): void
    {
        $events = ResponseListener::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
    }

    public function testAddsHeaderWhenCorrelationIdExists(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Correlation-ID');

        $request = new Request();
        $this->requestStack->push($request);
        $this->storage->set('test-id-123');

        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame('test-id-123', $response->headers->get('X-Correlation-ID'));
    }

    public function testDoesNotAddHeaderWhenNoCorrelationId(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Correlation-ID');

        $request = new Request();
        $this->requestStack->push($request);

        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-Correlation-ID'));
    }

    public function testIgnoresSubRequests(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Correlation-ID');

        $request = new Request();
        $this->requestStack->push($request);
        $this->storage->set('test-id-456');

        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-Correlation-ID'));
    }

    public function testUsesCustomHeaderName(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Request-ID');

        $request = new Request();
        $this->requestStack->push($request);
        $this->storage->set('custom-id-789');

        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('X-Request-ID'));
        $this->assertSame('custom-id-789', $response->headers->get('X-Request-ID'));
        $this->assertFalse($response->headers->has('X-Correlation-ID'));
    }

    public function testOverwritesExistingHeader(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Correlation-ID');

        $request = new Request();
        $this->requestStack->push($request);
        $this->storage->set('new-id-999');

        $response = new Response();
        $response->headers->set('X-Correlation-ID', 'old-id-111');
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertSame('new-id-999', $response->headers->get('X-Correlation-ID'));
    }

    public function testWorksWithDifferentResponseTypes(): void
    {
        $listener = new ResponseListener($this->storage, 'X-Correlation-ID');

        $request = new Request();
        $this->requestStack->push($request);
        $this->storage->set('test-id-json');

        // Test avec JSON response
        $response = new Response('{"test": true}', 200, ['Content-Type' => 'application/json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame('test-id-json', $response->headers->get('X-Correlation-ID'));
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }
}