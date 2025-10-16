# Symfony Correlation ID Bundle

A Symfony bundle to automatically manage correlation IDs across HTTP requests and responses, making it easy to trace and debug your application.

## Features

- **Automatic ID Management**: Extracts correlation ID from incoming request headers or generates a new UUID v4
- **Configurable & Secure**: Validate incoming IDs (length, format), trust or ignore incoming headers
- **Easy to Use**: Zero-config installation with sensible defaults
- **Production Ready**: 100% test coverage, follows Symfony best practices

## Installation

### 1. Install via Composer

```bash
composer require mdavid-dev/symfony-correlation-id-bundle
```

### 2. Enable the Bundle

```
<?php

return [
    // ...
    MdavidDev\SymfonyCorrelationIdBundle\SymfonyCorrelationIdBundle::class => ['all' => true],
];
```

### 3. That's it!

The bundle works out-of-the-box with default configuration.

## Quick Start

### Basic Usage

Basic Usage
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
```
# config/packages/correlation_id.yaml
correlation_id:
    header_name: 'X-Correlation-ID'
    generator: 'uuid_v4'
    trust_header: true
    
    validation:
        enabled: true
        max_length: 255
        pattern: null
    
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
        allow_option: true
```

### Configuration Examples
Custom Header Name
```
correlation_id:
    header_name: 'X-Request-ID'
```
Disable Trust Header
Always generate a new ID, ignoring incoming headers:
```
correlation_id:
    trust_header: false
```
Strict Validation
Validate incoming IDs with specific rules:
```
correlation_id:
    validation:
        enabled: true
        max_length: 36
        pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
```

## Usage in Your Application

### Access the Correlation ID
Inject the **CorrelationIdStorage** service:
```
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
        $$correlationId = $$this->correlationIdStorage->get();
        
        $this->logger->info('Fetching users', [
            'correlation_id' => $correlationId,
        ]);

        return $this->json(['users' => []]);
    }
}
```

### Available Methods
```
// Get current correlation ID
$$id = $$this->correlationIdStorage->get();

// Check if ID exists
if ($this->correlationIdStorage->has()) {
    // ...
}

// Set custom ID (advanced usage)
$this->correlationIdStorage->set('my-custom-id-123');

// Clear the ID
$this->correlationIdStorage->clear();
```

## Advanced Usage

### Custom ID Generator
Create your own generator:
```
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
```
# config/services.yaml
services:
    MdavidDev\SymfonyCorrelationIdBundle\Service\Generator\CorrelationIdGeneratorInterface:
        class: App\Service\CustomIdGenerator
```

## Testing
```
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