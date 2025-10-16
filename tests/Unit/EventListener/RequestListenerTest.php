<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\EventListener;

use MdavidDev\SymfonyCorrelationIdBundle\EventListener\RequestListener;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestListenerTest extends TestCase
{
    private RequestStack $requestStack;
    private CorrelationIdStorage $storage;
    private CorrelationIdValidator $validator;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->storage = new CorrelationIdStorage($this->requestStack);
        $this->validator = new CorrelationIdValidator(enabled: true, maxLength: 255, pattern: null);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testSubscribedEvents(): void
    {
        $events = RequestListener::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 512], $events[KernelEvents::REQUEST]);
    }

    public function testGeneratesIdWhenNoHeaderPresent(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn('generated-id-123');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($this->storage->has());
        $this->assertSame('generated-id-123', $this->storage->get());
    }

    public function testUsesHeaderWhenPresentAndValid(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->never())
            ->method('generate');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $request->headers->set('X-Correlation-ID', 'header-id-456');
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($this->storage->has());
        $this->assertSame('header-id-456', $this->storage->get());
    }

    public function testGeneratesIdWhenHeaderInvalid(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn('generated-id-789');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $request->headers->set('X-Correlation-ID', ''); // Invalide (vide)
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($this->storage->has());
        $this->assertSame('generated-id-789', $this->storage->get());
    }

    public function testGeneratesIdWhenTrustHeaderIsFalse(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn('new-generated-id');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            false // trust_header = false
        );

        $request = new Request();
        $request->headers->set('X-Correlation-ID', 'header-id-should-be-ignored');
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($this->storage->has());
        $this->assertSame('new-generated-id', $this->storage->get());
    }

    public function testDoesNothingWhenIdAlreadyExists(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->never())
            ->method('generate');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $this->requestStack->push($request);

        // Définir un ID existant
        $this->storage->set('existing-id');

        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // L'ID existant ne doit pas avoir changé
        $this->assertSame('existing-id', $this->storage->get());
    }

    public function testIgnoresSubRequests(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->never())
            ->method('generate');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($this->storage->has());
    }

    public function testTrimsWhitespaceFromHeader(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->never())
            ->method('generate');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Correlation-ID',
            true
        );

        $request = new Request();
        $request->headers->set('X-Correlation-ID', '  header-with-spaces  ');
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertSame('header-with-spaces', $this->storage->get());
    }

    public function testUsesCustomHeaderName(): void
    {
        $generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $generator->expects($this->never())
            ->method('generate');

        $listener = new RequestListener(
            $this->storage,
            $generator,
            $this->validator,
            'X-Request-ID', // Custom header name
            true
        );

        $request = new Request();
        $request->headers->set('X-Request-ID', 'custom-header-id');
        $this->requestStack->push($request);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertSame('custom-header-id', $this->storage->get());
    }
}