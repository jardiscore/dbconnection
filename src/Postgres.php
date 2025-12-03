<?php

declare(strict_types=1);

namespace JardisCore\DbConnection;

use JardisCore\DbConnection\Data\PostgresConfig;
use JardisCore\DbConnection\Connection\PdoConnection;
use RuntimeException;

/**
 * PostgresSQL database connection.
 * @phpstan-property PostgresConfig $config
 */
final class Postgres extends PdoConnection
{
    /**
     * @param PostgresConfig $config The PostgreSQL connection configuration
     * @throws RuntimeException On connection error
     */
    public function __construct(PostgresConfig $config)
    {
        $this->config = $config;
        $this->connect($this->buildDsn(), $config, $config->database);
    }

    protected function buildDsn(): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->config->host,
            $this->config->port,
            $this->config->database
        );
    }
}
