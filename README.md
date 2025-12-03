# 🚀 Jardis DbConnection

> **Enterprise-grade PHP database connection management with intelligent pooling and replication support**

![Build Status](https://github.com/jardisCore/dbconnection/actions/workflows/ci.yml/badge.svg)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-success.svg)](phpstan.neon)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-orange.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/coverage->92%25-brightgreen)](https://github.com/jardiscore/dbconnection)
> 
**DbConnection** is a production-ready PHP library that delivers professional PDO connection management with advanced connection pooling, load balancing, and automatic failover capabilities. Built for modern PHP applications requiring high availability and optimal performance.

---

## ✨ Why DbConnection?

### 🎯 Built for Scale
- **Smart Connection Pooling** - Automatic read/write splitting with intelligent load balancing
- **Replication Ready** - Native support for primary/replica database architectures
- **PHP-FPM Optimized** - Persistent connections for maximum performance
- **Auto-Failover** - Health checks with automatic reconnection and graceful degradation

### 💪 Production-Proven
- **Type-Safe** - Full PHP 8.2+ type declarations with readonly classes and strict mode
- **Extensively Tested** - 92%+ code coverage with comprehensive integration tests
- **PSR-12 Compliant** - Clean, maintainable code following PHP standards
- **PHPStan Level 8** - Maximum static analysis confidence

### 🔧 Developer-Friendly
- **Simple Factory Pattern** - Intuitive API with minimal configuration
- **Multiple Drivers** - MySQL/MariaDB, PostgreSQL, and SQLite support
- **Docker-First** - Complete development environment with one command
- **Well Documented** - Clear examples and comprehensive documentation

---

## 📦 Installation

```bash
composer require jardiscore/dbconnection
```

**Requirements:**
- PHP 8.2 or higher
- PDO extension
- Supported database driver (pdo_mysql, pdo_pgsql, or pdo_sqlite)

---

## 🎬 Quick Start

### Single Database Connection

```php
use JardisCore\DbConnection\MySql;
use JardisCore\DbConnection\Data\MySqlConfig;

// Create MySQL connection
$connection = new MySql(new MySqlConfig(
    host: 'localhost',
    user: 'app_user',
    password: 'secure_password',
    database: 'my_application'
));

// Get PDO instance
$pdo = $connection->pdo();
$users = $pdo->query('SELECT * FROM users')->fetchAll();

// Transaction support
$connection->beginTransaction();
try {
    // Your queries here
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
```

### Connection Pool with Replication

```php
use JardisCore\DbConnection\ConnectionPool;
use JardisCore\DbConnection\MySql;
use JardisCore\DbConnection\Data\MySqlConfig;
use JardisCore\DbConnection\Data\ConnectionPoolConfig;

// Configure primary and replicas
$primary = new MySqlConfig(
    host: 'primary.db.local',
    user: 'app_user',
    password: 'secure_password',
    database: 'production_db'
);

$replica1 = new MySqlConfig(
    host: 'replica1.db.local',
    user: 'readonly_user',
    password: 'secure_password',
    database: 'production_db'
);

$replica2 = new MySqlConfig(
    host: 'replica2.db.local',
    user: 'readonly_user',
    password: 'secure_password',
    database: 'production_db'
);

// Create connection pool
$pool = new ConnectionPool(
    writer: $primary,
    readers: [$replica1, $replica2],
    driverClass: MySql::class,
    config: new ConnectionPoolConfig(
        usePersistent: true,
        loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_ROUND_ROBIN,
        validateConnections: true,
        maxRetries: 3
    )
);

// Write operations go to primary
$writer = $pool->getWriter();
$writer->pdo()->exec('INSERT INTO orders (user_id, total) VALUES (123, 99.99)');

// Read operations are load-balanced across replicas
$reader = $pool->getReader();
$orders = $reader->pdo()->query('SELECT * FROM orders WHERE user_id = 123')->fetchAll();

// Monitor pool performance
$stats = $pool->getStats();
// ['reads' => 1543, 'writes' => 89, 'failovers' => 2, 'readers' => 2]
```

### Single Database with Pool Benefits

```php
// Use ConnectionPool even without replicas for health checks and stats
$pool = new ConnectionPool(
    writer: $dbConfig,
    readers: [], // Empty = uses writer for reads
    driverClass: MySql::class
);

// Same API, automatic fallback
$connection = $pool->getReader(); // Returns writer connection
```

---

## 🎛️ Supported Databases

### MySQL / MariaDB

```php
use JardisCore\DbConnection\MySql;
use JardisCore\DbConnection\Data\MySqlConfig;

$connection = new MySql(new MySqlConfig(
    host: 'localhost',
    user: 'username',
    password: 'password',
    database: 'mydb',
    port: 3306,
    charset: 'utf8mb4'
));
```

### PostgreSQL

```php
use JardisCore\DbConnection\Postgres;
use JardisCore\DbConnection\Data\PostgresConfig;

$connection = new Postgres(new PostgresConfig(
    host: 'localhost',
    user: 'username',
    password: 'password',
    database: 'mydb',
    port: 5432
));
```

### SQLite

```php
use JardisCore\DbConnection\SqLite;
use JardisCore\DbConnection\Data\SqliteConfig;

$connection = new SqLite(new SqliteConfig(
    path: '/path/to/database.sqlite'
    // or use ':memory:' for in-memory database
));
```

---

## 🔥 Advanced Features

### Load Balancing Strategies

**Round Robin** (Default) - Distributes requests evenly across replicas
```php
$config = new ConnectionPoolConfig(
    loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_ROUND_ROBIN
);
```

**Random** - Randomly selects replicas
```php
$config = new ConnectionPoolConfig(
    loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_RANDOM
);
```

**Weighted** - Weighted distribution (coming soon)
```php
$config = new ConnectionPoolConfig(
    loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_WEIGHTED
);
```

### Health Checks & Automatic Failover

```php
$config = new ConnectionPoolConfig(
    validateConnections: true,        // Enable health checks
    healthCheckCacheTtl: 30,         // Cache health status for 30s
    maxRetries: 3,                   // Try up to 3 replicas
    connectionTimeout: 5             // 5 second connection timeout
);

$pool = new ConnectionPool($primary, $replicas, MySql::class, $config);

// Automatically handles replica failures
$reader = $pool->getReader(); // Will skip unhealthy replicas
```

### Persistent Connections (PHP-FPM Optimization)

```php
$config = new ConnectionPoolConfig(
    usePersistent: true  // Reuse connections across requests
);

// Dramatic performance improvement in PHP-FPM environments
$pool = new ConnectionPool($primary, $replicas, MySql::class, $config);
```

### Connection Monitoring

```php
// Get detailed statistics
$stats = $pool->getStats();
echo "Total reads: {$stats['reads']}\n";
echo "Total writes: {$stats['writes']}\n";
echo "Failovers: {$stats['failovers']}\n";
echo "Active readers: {$stats['readers']}\n";

// Reset statistics
$pool->resetStats();
```

### Custom PDO Options

```php
use PDO;

$config = new MySqlConfig(
    host: 'localhost',
    user: 'username',
    password: 'password',
    database: 'mydb',
    options: [
        PDO::ATTR_TIMEOUT => 10,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
);
```

---

## 🧪 Development

This project uses Docker for all development tasks. **No local PHP installation required!**

### Setup

```bash
# Start containers and install dependencies
make install

# Or step by step
make start    # Start MySQL, MariaDB, PostgreSQL containers
make install  # Install composer dependencies
```

### Testing

```bash
# Run all tests
make phpunit

# Run with coverage report
make phpunit-coverage

# Generate HTML coverage report
make phpunit-coverage-html

# Run specific test
docker compose run --rm phpcli vendor/bin/phpunit tests/integration/ConnectionPoolTest.php
```

### Code Quality

```bash
# Check coding standards (PSR-12)
make phpcs

# Static analysis (PHPStan Level 8)
make phpstan
```

### Docker Commands

```bash
make start      # Start database containers
make stop       # Stop and remove containers
make restart    # Restart all containers
make shell      # Open shell in PHP container
```

---

## 🏗️ Architecture

### Factory Pattern
```
DbConnection (Factory)
    ├─→ MySql extends PdoConnection
    ├─→ Postgres extends PdoConnection
    └─→ SqLite extends PdoConnection
```

### Connection Pool
```
ConnectionPool
    ├─→ Writer (Primary Database)
    ├─→ Readers (Replica Databases)
    │   ├─→ Load Balancing
    │   ├─→ Health Checks
    │   └─→ Automatic Failover
    └─→ Statistics & Monitoring
```

### Key Components

- **`PdoConnection`** - Base connection class with PDO management
- **`ConnectionPool`** - Read/write splitting with load balancing
- **`ConnectionPoolConfig`** - Configuration for pool behavior
- **`DatabaseConfig`** - Type-safe configuration objects (MySqlConfig, PostgresConfig, SqliteConfig)

---

## 📊 Quality Metrics

- **Test Coverage:** 92%+ (141 Integration + Unit tests)
- **PHPStan Level:** 8 (Maximum strictness)
- **Coding Standard:** PSR-12 compliant
- **CI/CD:** Automated GitHub Actions pipeline
- **PHP Version:** 8.2+ with strict types and readonly classes

---

### Pre-commit Hook

Automatically installed via `composer install`:
- Validates branch naming convention
- Runs PHPCS on staged files
- Validates git user configuration

---

## 📝 License

This project is licensed under the **[PolyForm Noncommercial License 1.0.0](LICENSE)**.

---

## 🙏 Credits

**DbConnection** is developed and maintained by [Headgent Development](https://headgent.dev).

### Support

- **Issues:** [GitHub Issues](https://github.com/jardiscore/dbconnection/issues)
- **Email:** jardiscore@headgent.dev

---

## 🌟 Star This Project

If DbConnection helps your project, consider giving it a ⭐ on GitHub!

**Built with ❤️ by the Jardis Development Core team**
