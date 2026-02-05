<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\EventListener;

use Exception;
use MdavidDev\SymfonyCorrelationIdBundle\EventListener\ConsoleListener;
use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;
use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ConsoleListenerTest extends TestCase
{
    private CorrelationIdStorage $storage;
    private CorrelationIdGeneratorInterface&MockObject $generator;
    private CorrelationIdValidator $validator;
    private ConsoleListener $listener;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $this->storage = new CorrelationIdStorage($requestStack);
        $this->generator = $this->createMock(CorrelationIdGeneratorInterface::class);
        $this->validator = new CorrelationIdValidator(true, 255, null);
        
        $this->listener = new ConsoleListener(
            $this->storage,
            $this->generator,
            $this->validator,
            'CLI-',
            true
        );
    }

    public function testSubscribedEvents(): void
    {
        $events = ConsoleListener::getSubscribedEvents();

        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        $this->assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
        $this->assertArrayHasKey(ConsoleEvents::ERROR, $events);
    }

    public function testGeneratesIdWithPrefixWhenNoOptionProvided(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $command = $this->createMock(Command::class);
        
        $input->method('hasParameterOption')->with('--correlation-id')->willReturn(false);
        
        $this->generator->expects($this->once())->method('generate')->willReturn('uuid-123');

        $event = new ConsoleCommandEvent($command, $input, $output);
        $this->listener->onConsoleCommand($event);
        
        $this->assertSame('CLI-uuid-123', $this->storage->get());
    }

    public function testUsesOptionWhenProvidedAndValid(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $command = $this->createMock(Command::class);
        
        $input->method('hasParameterOption')->with('--correlation-id')->willReturn(true);
        $input->method('getParameterOption')->with('--correlation-id')->willReturn('custom-id');
        
        $this->generator->expects($this->never())->method('generate');

        $event = new ConsoleCommandEvent($command, $input, $output);
        $this->listener->onConsoleCommand($event);
        
        $this->assertSame('custom-id', $this->storage->get());
    }

    public function testGeneratesIdWhenOptionProvidedButInvalid(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $command = $this->createMock(Command::class);
        
        $input->method('hasParameterOption')->with('--correlation-id')->willReturn(true);
        $input->method('getParameterOption')->with('--correlation-id')->willReturn('   ');
        
        $this->generator->expects($this->once())->method('generate')->willReturn('uuid-456');

        $event = new ConsoleCommandEvent($command, $input, $output);
        $this->listener->onConsoleCommand($event);
        
        $this->assertSame('CLI-uuid-456', $this->storage->get());
    }

    public function testDoesNotAddOptionWhenAllowOptionIsFalse(): void
    {
        $listener = new ConsoleListener($this->storage, $this->generator, $this->validator, 'CLI-', false);
        
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $command = $this->createMock(Command::class);
        
        $this->generator->expects($this->once())->method('generate')->willReturn('uuid-789');

        $event = new ConsoleCommandEvent($command, $input, $output);
        $listener->onConsoleCommand($event);
        
        $this->assertSame('CLI-uuid-789', $this->storage->get());
    }

    public function testOnConsoleTerminateClearsStorage(): void
    {
        $command = $this->createMock(Command::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        
        $this->storage->set('some-id');
        $event = new ConsoleTerminateEvent($command, $input, $output, 0);
        $this->listener->onConsoleTerminate($event);
        $this->assertNull($this->storage->get());
    }

    public function testOnConsoleErrorClearsStorage(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $error = new Exception('error');
        
        $this->storage->set('some-id');
        $event = new ConsoleErrorEvent($input, $output, $error);
        $this->listener->onConsoleError($event);
        $this->assertNull($this->storage->get());
    }

    public function testOnConsoleCommandDoesNothingIfCommandIsNull(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        
        $event = new ConsoleCommandEvent(null, $input, $output);
        $this->listener->onConsoleCommand($event);
        $this->assertNull($this->storage->get());
    }
}
