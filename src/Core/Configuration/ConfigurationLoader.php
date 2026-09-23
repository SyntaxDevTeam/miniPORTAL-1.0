<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\DatabaseEngine;

final readonly class ConfigurationLoader
{
    public function __construct(private EnvironmentFileParser $parser = new EnvironmentFileParser())
    {
    }

    /**
     * Process environment values override the optional .env file.
     *
     * @param array<string, string> $environment
     */
    public function load(string $projectRoot, array $environment = []): ApplicationConfig
    {
        $fileValues = $this->fileValues($projectRoot . '/.env');
        $values = array_replace($fileValues, $environment);
        $appEnvironment = ApplicationEnvironment::fromEnvironment($values['MINIPORTAL_ENV'] ?? null);

        $databaseKeys = [
            'MINIPORTAL_DATABASE_ENGINE',
            'MINIPORTAL_DATABASE_HOST',
            'MINIPORTAL_DATABASE_PORT',
            'MINIPORTAL_DATABASE_NAME',
            'MINIPORTAL_DATABASE_USER',
            'MINIPORTAL_DATABASE_PASSWORD',
        ];
        $configured = array_filter($databaseKeys, static fn (string $key): bool => array_key_exists($key, $values));
        if ($configured === []) {
            return new ApplicationConfig($appEnvironment);
        }

        foreach ($databaseKeys as $key) {
            if (!array_key_exists($key, $values)) {
                throw new ConfigurationException(sprintf('Database configuration is incomplete; missing %s.', $key));
            }
        }

        $engine = match (strtolower($values['MINIPORTAL_DATABASE_ENGINE'])) {
            'mysql', 'mariadb' => DatabaseEngine::MySql,
            'pgsql', 'postgres', 'postgresql' => DatabaseEngine::PostgreSql,
            default => throw new ConfigurationException('Unsupported database engine.'),
        };
        $port = filter_var($values['MINIPORTAL_DATABASE_PORT'], FILTER_VALIDATE_INT);
        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new ConfigurationException('Database port must be between 1 and 65535.');
        }

        $database = new DatabaseSettings(
            $engine,
            $values['MINIPORTAL_DATABASE_HOST'],
            $port,
            $values['MINIPORTAL_DATABASE_NAME'],
            $values['MINIPORTAL_DATABASE_USER'],
            $values['MINIPORTAL_DATABASE_PASSWORD'],
        );
        try {
            $database->connectionConfig();
        } catch (\InvalidArgumentException $exception) {
            throw new ConfigurationException('Invalid database configuration.', previous: $exception);
        }

        return new ApplicationConfig($appEnvironment, $database);
    }

    /** @return array<string, string> */
    private function fileValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ConfigurationException('Unable to read environment file.');
        }
        return $this->parser->parse($contents);
    }
}
