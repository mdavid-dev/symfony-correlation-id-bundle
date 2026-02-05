<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\Service\Generator;

use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\UuidV4Generator;
use PHPUnit\Framework\TestCase;

class UuidV4GeneratorTest extends TestCase
{
    private UuidV4Generator $generator;

    protected function setUp(): void
    {
        $this->generator = new UuidV4Generator();
    }

    public function testGenerateReturnsString(): void
    {
        $id = $this->generator->generate();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    public function testGenerateReturnsValidUuidV4Format(): void
    {
        $id = $this->generator->generate();

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, $id);
    }

    public function testGenerateReturnsUniqueIds(): void
    {
        $ids = [];

        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->generator->generate();
        }

        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    public function testGenerateReturnsCorrectLength(): void
    {
        $id = $this->generator->generate();

        $this->assertSame(36, strlen($id));
    }

    public function testGenerateIsIdempotent(): void
    {
        $id1 = $this->generator->generate();
        $id2 = $this->generator->generate();

        $this->assertNotSame($id1, $id2);
    }
}
