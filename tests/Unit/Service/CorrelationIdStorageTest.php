<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\Service;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CorrelationIdStorageTest extends TestCase
{
    private RequestStack $requestStack;
    private CorrelationIdStorage $storage;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->storage = new CorrelationIdStorage($this->requestStack);
    }

    public function testGetReturnsNullWhenNoIdIsSet(): void
    {
        $this->assertNull($this->storage->get());
    }

    public function testHasReturnsFalseWhenNoIdIsSet(): void
    {
        $this->assertFalse($this->storage->has());
    }

    public function testSetAndGetWithRequest(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $correlationId = 'test-correlation-id-123';
        $this->storage->set($correlationId);

        $this->assertTrue($this->storage->has());
        $this->assertSame($correlationId, $this->storage->get());
    }

    public function testSetAndGetWithoutRequest(): void
    {
        $correlationId = 'fallback-id-456';
        $this->storage->set($correlationId);

        $this->assertTrue($this->storage->has());
        $this->assertSame($correlationId, $this->storage->get());
    }

    public function testClearRemovesIdWithRequest(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $this->storage->set('test-id');
        $this->assertTrue($this->storage->has());

        $this->storage->clear();
        $this->assertFalse($this->storage->has());
        $this->assertNull($this->storage->get());
    }

    public function testClearRemovesIdWithoutRequest(): void
    {
        $this->storage->set('test-id');
        $this->assertTrue($this->storage->has());

        $this->storage->clear();
        $this->assertFalse($this->storage->has());
        $this->assertNull($this->storage->get());
    }

    public function testMultipleRequestsInStack(): void
    {
        $request1 = new Request();
        $request2 = new Request();

        $this->requestStack->push($request1);
        $this->storage->set('id-1');

        $this->requestStack->push($request2);
        $this->storage->set('id-2');

        $this->assertSame('id-2', $this->storage->get());

        $this->requestStack->pop();

        $this->assertSame('id-1', $this->storage->get());
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $this->storage->set('first-id');
        $this->assertSame('first-id', $this->storage->get());

        $this->storage->set('second-id');
        $this->assertSame('second-id', $this->storage->get());
    }

    public function testFallbackIsUsedWhenNoRequest(): void
    {
        $this->storage->set('fallback-id');
        $this->assertSame('fallback-id', $this->storage->get());

        $request = new Request();
        $this->requestStack->push($request);

        $this->assertNull($this->storage->get());

        $this->storage->set('request-id');
        $this->assertSame('request-id', $this->storage->get());
    }
}
