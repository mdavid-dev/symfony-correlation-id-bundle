# Symfony Correlation ID Bundle

A Symfony bundle to automatically manage correlation IDs across HTTP requests and responses, making it easy to trace and debug your application.

## Features

- **Automatic ID Management**: Extracts correlation ID from incoming request headers or generates a new UUID v4
- **Monolog Integration**: Automatically adds correlation ID to all logs
- **Configurable & Secure**: Validate incoming IDs (length, format), trust or ignore incoming headers
- **Easy to Use**: Zero-config installation with sensible defaults
- **Production Ready**: 100% test coverage, follows Symfony best practices

## Installation

### 1. Install via Composer

```bash
composer require mdavid-dev/symfony-correlation-id-bundle
```

### 2. Enable the Bundle

```php
<?php

return [
    // ...
    MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle::class => ['all' => true],
];
```

### 3. That's it!

The bundle works out-of-the-box with default configuration.

## Quick Start

Once installed, the bundle automatically:

1. Reads the X-Correlation-ID header from incoming requests
2. Generates a UUID v4 if the header is missing
3. Adds the correlation ID to all response headers

**Example Request:**
```
GET /api/users HTTP/1.1
Host: example.com
X-Correlation-ID: 550e8400-e29b-41d4-a716-446655440000
```

**Example Response:**
```
HTTP/1.1 200 OK
Content-Type: application/json
X-Correlation-ID: 550e8400-e29b-41d4-a716-446655440000

{"users": [...]}
```

## Configuration

### Default Configuration
```yaml
# config/packages/correlation_id.yaml
correlation_id:
    header_name: 'X-Correlation-ID'
    generator: 'uuid_v4'
    trust_header: true
    
    validation:
        enabled: true
        max_length: 255
        pattern: '/^[a-zA-Z0-9-_]+$/'
    
    monolog:
        enabled: true
        key: 'correlation_id'
    
    http_client:
        enabled: true
    
    messenger:
        enabled: true
    
    cli:
        enabled: true
        prefix: 'CLI-'
        allow_env_var: true
```

### Validation & Security

By default, the bundle validates correlation IDs to prevent malicious input:

```yaml
correlation_id:
    validation:
        enabled: true
        max_length: 255
        pattern: '/^[a-zA-Z0-9-_]+$/'  # Default: alphanumeric, dashes, underscores only
```

**Security features:**
- Rejects empty values
- Limits length to prevent buffer overflow attacks
- Blocks special characters that could be used for injection attacks (XSS, SQL injection, shell injection)
- Automatically generates a safe ID if validation fails

**Customize the pattern** for your needs:

#### UUID v4 only (strictest)
```yaml
correlation_id:
    validation:
        max_length: 36
        pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
```

#### Allow more characters
```yaml
correlation_id:
    validation:
        pattern: '/^[a-zA-Z0-9-_:]+$/'  # Add colon for custom formats like "APP:REQ:123"
```

#### Disable pattern validation (not recommended)
```yaml
correlation_id:
    validation:
        pattern: null  # Only length validation will apply
```

### Configuration Examples
Custom Header Name
```yaml
correlation_id:
    header_name: 'X-Request-ID'
```
Disable Trust Header
Always generate a new ID, ignoring incoming headers:
```yaml
correlation_id:
    trust_header: false
```
#### Enable Monolog Integration
Automatically add correlation ID to all logs:
```yaml
correlation_id:
    monolog:
        enabled: true
        key: 'correlation_id'  # Key name in log extra data
```
Disable Monolog integration:
```yaml
correlation_id:
    monolog:
        enabled: false
```

## Usage in Your Application

### Access the Correlation ID
Inject the **CorrelationIdStorage** service:
```php
<?php

namespace App\Controller;

use MdavidDev\SymfonyCorrelationIdBundle\Service\CorrelationIdStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private readonly CorrelationIdStorage $correlationIdStorage
    ) {
    }

    #[Route('/api/users', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $correlationId = $this->correlationIdStorage->get();
        
        $this->logger->info('Fetching users', [
            'correlation_id' => $correlationId,
        ]);

        return $this->json(['users' => []]);
    }
}
```

### Available Methods
```php
// Get current correlation ID
$id = $this->correlationIdStorage->get();

// Check if ID exists
if ($this->correlationIdStorage->has()) {
    // ...
}

// Set custom ID (advanced usage)
$this->correlationIdStorage->set('my-custom-id-123');

// Clear the ID
$this->correlationIdStorage->clear();
```

## Monolog Integration

### Automatic Logging
When Monolog integration is enabled (default), the correlation ID is automatically added to all log entries in the `extra` field.
**Example log output:**
```json
{
  "message": "User login successful",
  "context": {
    "user_id": 123
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "app",
  "datetime": "2024-10-17T10:30:45+00:00",
  "extra": {
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```
### Custom Log Key
Change the key name used in logs:
```yaml
correlation_id:
    monolog:
        key: 'request_id'  # Will appear as "request_id" in logs
```
### Requirements
Monolog integration requires the monolog/monolog package:
```bash
composer require monolog/monolog
```
If Monolog is not installed, the integration is automatically disabled.

## CLI Integration

When CLI integration is enabled (default), the bundle manages correlation IDs for Symfony Console commands.

### Environment Variable
If `cli.allow_env_var` is `true` (default), you can pass a correlation ID via the `CORRELATION_ID` environment variable:

```bash
CORRELATION_ID=custom-id-123 php bin/console app:my-command
```

This is particularly useful when executing commands from a Process within an HTTP request context:

```php
use Symfony\Component\Process\Process;

class MyService
{
    public function __construct(
        private readonly CorrelationIdStorage $correlationIdStorage
    ) {}
    
    public function executeCommand(): void
    {
        // Get current correlation ID from HTTP request
        $correlationId = $this->correlationIdStorage->get();
        
        // Pass it to the console command via environment variable
        $process = new Process(
            ['php', 'bin/console', 'app:my-command'],
            env: ['CORRELATION_ID' => $correlationId]
        );
        
        $process->run();
    }
}
```

### Automatic ID Generation
If no environment variable is provided, an ID is automatically generated using the configured generator and prefixed with `cli.prefix` (default: `CLI-`):

**Example output for a generated ID:** `CLI-550e8400-e29b-41d4-a716-446655440000`

### Access in Commands
You can access the ID in your commands just like in controllers:

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $correlationId = $this->correlationIdStorage->get();
    // ...
}
```

## Advanced Usage

### Custom ID Generator
Create your own generator:
```php
<?php

namespace App\Service;

use MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface;

class CustomIdGenerator implements CorrelationIdGeneratorInterface
{
    public function generate(): string
    {
        return uniqid('APP-', true);
    }
}
```
Configure it:
```yaml
# config/services.yaml
services:
    MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface:
        class: App\Service\CustomIdGenerator
```

## Testing
```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html build/coverage
```

## Requirements
- PHP 8.2 or higher
- Symfony 6.4 or 7.0+

## License
This bundle is released under the MIT License.

## Contributing
Contributions are welcome! Please submit a Pull Request.
