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
        // Pas de requête dans le stack
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

        // La requête courante (request2) doit avoir 'id-2'
        $this->assertSame('id-2', $this->storage->get());

        // Retirer request2
        $this->requestStack->pop();

        // Maintenant on doit avoir 'id-1' (de request1)
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
        // Définir un ID sans requête
        $this->storage->set('fallback-id');
        $this->assertSame('fallback-id', $this->storage->get());

        // Ajouter une requête
        $request = new Request();
        $this->requestStack->push($request);

        // Le fallback ne doit plus être utilisé (pas d'ID dans la requête)
        $this->assertNull($this->storage->get());

        // Définir un ID dans la requête
        $this->storage->set('request-id');
        $this->assertSame('request-id', $this->storage->get());
    }
}