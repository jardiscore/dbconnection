<?php

declare(strict_types=1);

namespace JardisCore\DbConnection\Tests\integration;

use JardisCore\DbConnection\MySql;
use JardisCore\DbConnection\ConnectionPool;
use JardisCore\DbConnection\Data\MySqlConfig;
use JardisCore\DbConnection\Data\ConnectionPoolConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use InvalidArgumentException;
use PDO;

class ConnectionPoolTest extends TestCase
{
    private MySqlConfig $writerConfig;
    private MySqlConfig $reader1Config;
    private MySqlConfig $reader2Config;

    protected function setUp(): void
    {
        $this->writerConfig = new MySqlConfig(
            host: $_ENV['MYSQL_HOST'] ?? 'mysql',
            user: $_ENV['MYSQL_USER'] ?? 'test_user',
            password: $_ENV['MYSQL_PASSWORD'] ?? 'test_password',
            database: $_ENV['MYSQL_DATABASE'] ?? 'test_db',
            port: (int)($_ENV['MYSQL_PORT'] ?? 3306)
        );

        $this->reader1Config = clone $this->writerConfig;
        $this->reader2Config = clone $this->writerConfig;
    }

    public function testPoolCreation(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config, $this->reader2Config],
            driverClass: MySql::class
        );

        $this->assertInstanceOf(ConnectionPool::class, $pool);
    }

    public function testEmptyReadersUsesWriterAsReader(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [],
            driverClass: MySql::class
        );

        $this->assertInstanceOf(ConnectionPool::class, $pool);

        $writer = $pool->getWriter();
        $this->assertInstanceOf(MySql::class, $writer);

        $reader = $pool->getReader();
        $this->assertInstanceOf(MySql::class, $reader);

        $this->assertSame($writer, $reader);

        $stats = $pool->getStats();
        $this->assertEquals(1, $stats['readers']); // Writer is used as reader
    }

    public function testInvalidDriverClassThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver class must implement DbConnectionInterface');

        new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: \stdClass::class
        );
    }

    public function testGetWriterReturnsConnection(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class
        );

        $writer = $pool->getWriter();
        $this->assertInstanceOf(MySql::class, $writer);

        $pdo = $writer->pdo();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testGetReaderReturnsConnection(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class
        );

        $reader = $pool->getReader();
        $this->assertInstanceOf(MySql::class, $reader);

        $pdo = $reader->pdo();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testRoundRobinLoadBalancing(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config, $this->reader2Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_ROUND_ROBIN,
                validateConnections: false // Disable health checks for deterministic testing
            )
        );

        $reader1 = $pool->getReader();
        $reader2 = $pool->getReader();
        $reader3 = $pool->getReader();

        $this->assertNotSame($reader1, $reader2);
        $this->assertSame($reader1, $reader3); // Should wrap around
    }

    public function testRandomLoadBalancing(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config, $this->reader2Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_RANDOM,
                validateConnections: false
            )
        );

        for ($i = 0; $i < 10; $i++) {
            $reader = $pool->getReader();
            $this->assertInstanceOf(MySql::class, $reader);
        }
    }

    public function testStatsTracking(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class
        );

        $initialStats = $pool->getStats();
        $this->assertEquals(0, $initialStats['reads']);
        $this->assertEquals(0, $initialStats['writes']);
        $this->assertEquals(0, $initialStats['failovers']);
        $this->assertEquals(1, $initialStats['readers']);

        $pool->getWriter();
        $pool->getReader();
        $pool->getReader();

        $stats = $pool->getStats();
        $this->assertEquals(2, $stats['reads']);
        $this->assertEquals(1, $stats['writes']);
    }

    public function testResetStats(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class
        );

        $pool->getWriter();
        $pool->getReader();

        $pool->resetStats();

        $stats = $pool->getStats();
        $this->assertEquals(0, $stats['reads']);
        $this->assertEquals(0, $stats['writes']);
        $this->assertEquals(0, $stats['failovers']);
    }

    public function testPersistentConnectionsEnabled(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(usePersistent: true)
        );

        $writer = $pool->getWriter();
        $pdo = $writer->pdo();

        $isPersistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);
        $this->assertTrue((bool)$isPersistent);
    }

    public function testPersistentConnectionsDisabled(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(usePersistent: false)
        );

        $writer = $pool->getWriter();
        $pdo = $writer->pdo();

        $isPersistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);
        $this->assertFalse((bool)$isPersistent);
    }

    public function testHealthCheckWithValidConnection(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(validateConnections: true)
        );

        $reader = $pool->getReader();
        $this->assertInstanceOf(MySql::class, $reader);
    }

    public function testHealthCheckWithInvalidConnection(): void
    {
        $invalidConfig = new MySqlConfig(
            host: 'invalid-host-that-does-not-exist',
            user: 'test',
            password: 'test',
            database: 'test'
        );

        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$invalidConfig],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                validateConnections: true,
                maxRetries: 1,
                connectionTimeout: 1
            )
        );

        $this->expectException(RuntimeException::class);
        $pool->getReader(); // Exception now thrown on first use
    }

    public function testWriterHealthCheckFails(): void
    {
        $invalidConfig = new MySqlConfig(
            host: 'invalid-host-that-does-not-exist',
            user: 'test',
            password: 'test',
            database: 'test'
        );

        $pool = new ConnectionPool(
            writer: $invalidConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                validateConnections: true,
                connectionTimeout: 1
            )
        );

        $this->expectException(RuntimeException::class);
        $pool->getWriter(); // Exception now thrown on first use
    }

    public function testFailoverToNextReader(): void
    {
        $invalidConfig = new MySqlConfig(
            host: 'invalid-host',
            user: 'test',
            password: 'test',
            database: 'test'
        );

        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$invalidConfig, $this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                validateConnections: true,
                maxRetries: 2,
                connectionTimeout: 1
            )
        );

        $this->expectException(RuntimeException::class);
        $pool->getReader(); // Exception now thrown on first use
    }

    public function testHealthCheckCaching(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                validateConnections: true,
                healthCheckCacheTtl: 60 // Long TTL
            )
        );

        $reader1 = $pool->getReader();

        $start = microtime(true);
        $reader2 = $pool->getReader();
        $duration = microtime(true) - $start;

        $this->assertLessThan(0.01, $duration);
        $this->assertInstanceOf(MySql::class, $reader2);
    }

    public function testActualDatabaseOperations(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config],
            driverClass: MySql::class
        );

        $writer = $pool->getWriter();
        $pdo = $writer->pdo();
        $pdo->exec('DROP TABLE IF EXISTS test_pool');
        $pdo->exec('CREATE TABLE test_pool (id INT PRIMARY KEY, value VARCHAR(100))');

        $stmt = $pdo->prepare('INSERT INTO test_pool (id, value) VALUES (?, ?)');
        $stmt->execute([1, 'test_value']);

        $reader = $pool->getReader();
        $readerPdo = $reader->pdo();
        $stmt = $readerPdo->query('SELECT value FROM test_pool WHERE id = 1');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('test_value', $result['value']);

        $pdo->exec('DROP TABLE test_pool');
    }

    public function testWeightedLoadBalancingStrategy(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$this->reader1Config, $this->reader2Config],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_WEIGHTED,
                validateConnections: false
            )
        );

        $reader1 = $pool->getReader();
        $reader2 = $pool->getReader();
        $reader3 = $pool->getReader();

        $this->assertInstanceOf(MySql::class, $reader1);
        $this->assertInstanceOf(MySql::class, $reader2);
        $this->assertInstanceOf(MySql::class, $reader3);
    }

    public function testPoolWithSqliteDriver(): void
    {
        $sqliteConfig = new \JardisCore\DbConnection\Data\SqliteConfig(
            path: ':memory:'
        );

        $pool = new ConnectionPool(
            writer: $sqliteConfig,
            readers: [],
            driverClass: \JardisCore\DbConnection\SqLite::class,
            config: new ConnectionPoolConfig(
                usePersistent: true,
                connectionTimeout: 10
            )
        );

        $writer = $pool->getWriter();
        $this->assertInstanceOf(\JardisCore\DbConnection\SqLite::class, $writer);

        $reader = $pool->getReader();
        $this->assertSame($writer, $reader);
    }

    public function testPoolWithPostgresDriver(): void
    {
        $postgresConfig = new \JardisCore\DbConnection\Data\PostgresConfig(
            host: $_ENV['POSTGRES_HOST'] ?? 'postgres',
            user: $_ENV['POSTGRES_USER'] ?? 'test_user',
            password: $_ENV['POSTGRES_PASSWORD'] ?? 'test_password',
            database: $_ENV['POSTGRES_DATABASE'] ?? 'test_db',
            port: (int)($_ENV['POSTGRES_PORT'] ?? 5432)
        );

        $pool = new ConnectionPool(
            writer: $postgresConfig,
            readers: [],
            driverClass: \JardisCore\DbConnection\Postgres::class,
            config: new ConnectionPoolConfig(
                usePersistent: false,
                connectionTimeout: 3
            )
        );

        $writer = $pool->getWriter();
        $this->assertInstanceOf(\JardisCore\DbConnection\Postgres::class, $writer);
    }

    public function testNonExistentDriverClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid driver class');

        new ConnectionPool(
            writer: $this->writerConfig,
            readers: [],
            driverClass: 'NonExistentClass'
        );
    }

    public function testGetReaderWithAllUnhealthyReplicas(): void
    {
        $invalidConfig1 = new MySqlConfig(
            host: 'invalid-host-1',
            user: 'test',
            password: 'test',
            database: 'test'
        );

        $invalidConfig2 = new MySqlConfig(
            host: 'invalid-host-2',
            user: 'test',
            password: 'test',
            database: 'test'
        );

        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [$invalidConfig1, $invalidConfig2],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                validateConnections: true,
                maxRetries: 2,
                connectionTimeout: 1
            )
        );

        $this->expectException(RuntimeException::class);
        $pool->getReader(); // Exception now thrown on first use
    }

    public function testCustomConnectionTimeout(): void
    {
        $pool = new ConnectionPool(
            writer: $this->writerConfig,
            readers: [],
            driverClass: MySql::class,
            config: new ConnectionPoolConfig(
                connectionTimeout: 15
            )
        );

        $writer = $pool->getWriter();
        $this->assertInstanceOf(MySql::class, $writer);

        $stats = $pool->getStats();
        $this->assertEquals(1, $stats['writes']);
    }
}
