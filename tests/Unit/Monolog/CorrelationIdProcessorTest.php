<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\Monolog;

use DateTimeImmutable;
use MdavidDev\SymfonyCorrelationIdBundle\Monolog\CorrelationIdProcessor;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class CorrelationIdProcessorTest extends TestCase
{
    private CorrelationIdStorage $storage;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $this->storage = new CorrelationIdStorage($requestStack);
    }

    public function testAddsCorrelationIdToLogRecord(): void
    {
        $this->storage->set('test-id-123');

        $processor = new CorrelationIdProcessor($this->storage, 'correlation_id');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test log message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('correlation_id', $processedRecord->extra);
        $this->assertSame('test-id-123', $processedRecord->extra['correlation_id']);
    }

    public function testDoesNotModifyRecordWhenNoCorrelationId(): void
    {
        $processor = new CorrelationIdProcessor($this->storage, 'correlation_id');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test log message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertArrayNotHasKey('correlation_id', $processedRecord->extra);
        $this->assertSame($record, $processedRecord);
    }

    public function testUsesCustomKey(): void
    {
        $this->storage->set('custom-key-id');

        $processor = new CorrelationIdProcessor($this->storage, 'request_id');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test log message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('request_id', $processedRecord->extra);
        $this->assertSame('custom-key-id', $processedRecord->extra['request_id']);
        $this->assertArrayNotHasKey('correlation_id', $processedRecord->extra);
    }

    public function testPreservesExistingExtra(): void
    {
        $this->storage->set('test-id-456');

        $processor = new CorrelationIdProcessor($this->storage, 'correlation_id');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test log message',
            context: [],
            extra: ['existing_key' => 'existing_value']
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('existing_key', $processedRecord->extra);
        $this->assertSame('existing_value', $processedRecord->extra['existing_key']);
        $this->assertArrayHasKey('correlation_id', $processedRecord->extra);
        $this->assertSame('test-id-456', $processedRecord->extra['correlation_id']);
    }

    public function testWorksWithDifferentLogLevels(): void
    {
        $this->storage->set('level-test-id');

        $processor = new CorrelationIdProcessor($this->storage, 'correlation_id');

        $levels = [Level::Debug, Level::Info, Level::Warning, Level::Error, Level::Critical];

        foreach ($levels as $level) {
            $record = new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'app',
                level: $level,
                message: 'Test message',
                context: [],
                extra: []
            );

            $processedRecord = $processor($record);

            $this->assertArrayHasKey('correlation_id', $processedRecord->extra);
            $this->assertSame('level-test-id', $processedRecord->extra['correlation_id']);
        }
    }
}