<?php

declare(strict_types=1);

namespace DoctrineCockroachDB;

use Doctrine\Bundle\DoctrineBundle;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Tools\DsnParser;
use DoctrineCockroachDB\Driver\CockroachDBDriver;

/**
 * @psalm-import-type Params from DriverManager
 * @noinspection PhpUnused
 */
class ConnectionFactory
{
    private const CRDB_DRIVER_ALIASES = ['crdb', 'pdo-crdb'];
    private const DEFAULT_SCHEME_MAP = [
        'db2' => 'ibm_db2',
        'mssql' => 'pdo_sqlsrv',
        'mysql' => 'pdo_mysql',
        'mysql2' => 'pdo_mysql', // Amazon RDS, for some weird reason
        'postgres' => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
        'sqlite3' => 'pdo_sqlite',
    ];

    private readonly DsnParser $dsnParser;

    public function __construct(
        private readonly DoctrineBundle\ConnectionFactory $decorated,
        DsnParser|null $dsnParser = null,
    ) {
        $this->dsnParser = $dsnParser ?? new DsnParser(self::DEFAULT_SCHEME_MAP);
    }

    /**
     * @psalm-param Params $params
     * @return Params
     * @throws Exception
     */
    private function parseDatabaseUrl(array $params): array
    {
        if (!isset($params['url'])) {
            return $params;
        }

        try {
            $parsedParams = $this->dsnParser->parse($params['url']);
        } catch (MalformedDsnException $e) {
            throw new MalformedDsnException('Malformed parameter "url".', 0, $e);
        }

        if (
            isset($parsedParams['driver'])
            && in_array($parsedParams['driver'], self::CRDB_DRIVER_ALIASES, true)
        ) {
            $parsedParams['driver'] = 'pdo_pgsql';
            $parsedParams['driverClass'] = CockroachDBDriver::class;
        } elseif (isset($parsedParams['driver'])) {
            // The requested driver from the URL scheme takes precedence
            // over the default custom driver from the connection parameters (if any).
            unset($params['driverClass']);
        }

        $params = array_merge($params, $parsedParams);

        // If a schemaless connection URL is given, we require a default driver or default custom driver
        // as connection parameter.
        if (!isset($params['driverClass']) && !isset($params['driver'])) {
            throw DriverRequired::new($params['url']);
        }

        unset($params['url']);

        return $params;
    }

    /**
     * @param EventManager|array<string, string>|null $eventManagerOrMappingTypes
     * @param array<string, string> $deprecatedMappingTypes
     * @psalm-param Params $params
     * @throws Exception
     */
    public function createConnection(
        array $params,
        Configuration|null $config = null,
        EventManager|array|null $eventManagerOrMappingTypes = [],
        array $deprecatedMappingTypes = [],
    ): Connection {
        $params = $this->parseDatabaseUrl($params);

        if (null !== $eventManagerOrMappingTypes) {
            return $this->decorated->createConnection(
                params: $params,
                config: $config,
                eventManagerOrMappingTypes: $eventManagerOrMappingTypes,
            );
        }

        if ([] !== $deprecatedMappingTypes) {
            return $this->decorated->createConnection(
                params: $params,
                config: $config,
                deprecatedMappingTypes: $deprecatedMappingTypes,
            );
        }

        return $this->decorated->createConnection(
            params: $params,
            config: $config,
        );
    }
}
