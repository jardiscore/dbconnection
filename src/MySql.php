<?php

declare(strict_types=1);

namespace JardisCore\DbConnection;

use JardisCore\DbConnection\Data\MySqlConfig;
use JardisCore\DbConnection\Connection\PdoConnection;
use RuntimeException;

/**
 * MySQL database connection.
 * @phpstan-property MySqlConfig $config
 */
final class MySql extends PdoConnection
{
    /**
     * @param MySqlConfig $config The MySQL connection configuration
     * @throws RuntimeException On connection error
     */
    public function __construct(MySqlConfig $config)
    {
        $this->config = $config;
        $this->connect($this->buildDsn(), $config, $config->database);
    }

    protected function buildDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->host,
            $this->config->port,
            $this->config->database,
            $this->config->charset
        );
    }
}
