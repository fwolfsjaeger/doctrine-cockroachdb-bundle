<?php

declare(strict_types=1);

namespace DoctrineCockroachDB;

use Doctrine\Bundle\DoctrineBundle;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Tools\DsnParser;
use DoctrineCockroachDB\Driver\CockroachDBDriver;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * @psalm-import-type Params from DriverManager
 */
#[AsDecorator(decorates: 'doctrine.dbal.connection_factory')]
class ConnectionFactory
{
    private const CRDB_DRIVER_ALIASES = ['crdb', 'pdo-crdb'];
    private const DRIVER_SCHEME_ALIASES = [
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

    public function __construct(
        private DoctrineBundle\ConnectionFactory $decorated,
    ) {
        // just for constructor property promotion
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
            $dsnParser = new DsnParser(self::DRIVER_SCHEME_ALIASES);
            $parsedParams = $dsnParser->parse($params['url']);
        } catch (MalformedDsnException $e) {
            throw new Exception('Malformed parameter "url".', 0, $e);
        }

        if (!empty($parsedParams['driver']) && in_array($parsedParams['driver'], self::CRDB_DRIVER_ALIASES, true)) {
            $parsedParams['driver'] = 'pdo_pgsql';
            $parsedParams['driverClass'] = CockroachDBDriver::class;
        }

        if (isset($parsedParams['driver'])) {
            // The requested driver from the URL scheme takes precedence
            // over the default custom driver from the connection parameters (if any).
            unset($params['driverClass']);
        }

        $params = array_merge($params, $parsedParams);

        // If a schemaless connection URL is given, we require a default driver or default custom driver
        // as connection parameter.
        if (!isset($params['driverClass']) && !isset($params['driver'])) {
            throw Exception::driverRequired($params['url']);
        }

        unset($params['url']);

        return $params;
    }

    /**
     * @param array<string, string> $mappingTypes
     * @psalm-param Params $params
     * @throws Exception
     */
    public function createConnection(
        array $params,
        ?Configuration $config = null,
        ?EventManager $eventManager = null,
        array $mappingTypes = [],
    ): Connection {
        $params = $this->parseDatabaseUrl($params);

        return $this->decorated->createConnection($params, $config, $eventManager, $mappingTypes);
    }
}
