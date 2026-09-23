<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo;

use PDO;
use PDOException;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\ProviderUnavailable;

final class PdoDatabaseFactory
{
    public function connect(PdoConnectionConfig $config): Database
    {
        if ($config->engine !== null && !extension_loaded($config->engine->requiredExtension())) {
            throw new ProviderUnavailable(sprintf(
                'Database engine %s requires the PHP extension %s.',
                $config->engine->value,
                $config->engine->requiredExtension(),
            ));
        }

        $options = $config->options;
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;

        try {
            $pdo = new PDO(
                $config->dsn,
                $config->username,
                $config->password,
                $options,
            );
        } catch (PDOException $exception) {
            throw new ProviderUnavailable(
                'Unable to establish database connection.',
                previous: $exception,
            );
        }

        return new PdoDatabase($pdo);
    }
}
