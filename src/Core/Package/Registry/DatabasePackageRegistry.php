<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

/** Persists release metadata; only the active table controls runtime selection. */
final readonly class DatabasePackageRegistry implements AtomicPackageRegistry
{
    private string $releases;
    private string $active;

    public function __construct(private Database $database, private ManifestParser $parser)
    {
        $scope = new StorageNamespace(DatabasePackageRegistryMigration::OWNER_ID);
        $this->releases = $scope->table('releases')->value;
        $this->active = $scope->table('active')->value;
    }

    public function add(PackageRelease $release): void
    {
        $this->database->execute(new SqlStatement(sprintf(
            'INSERT INTO %s (package_id, version, manifest_json, release_path, state) '
            . 'VALUES (:id, :version, :manifest, :path, :state)',
            $this->releases,
        ), $this->values($release)));
    }

    public function save(PackageRelease $release): void
    {
        $existing = $this->find($release->manifest->id, $release->manifest->version);
        if ($existing === null) {
            throw new \LogicException('Package release is not registered.');
        }
        if ($existing->manifest != $release->manifest || $existing->releasePath !== $release->releasePath) {
            throw new \LogicException('Registered package release metadata is immutable.');
        }
        $changed = $this->database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET state = :target WHERE package_id = :id AND version = :version AND state = :current',
            $this->releases,
        ), [
            'target' => $release->state->value,
            'id' => $release->manifest->id,
            'version' => $release->manifest->version,
            'current' => $existing->state->value,
        ]));
        if ($changed !== 1) {
            throw new \RuntimeException('Package release state changed concurrently.');
        }
    }

    public function commitTransition(
        PackageRelease $before,
        PackageRelease $after,
        bool $makeActive,
        bool $clearActive,
    ): void {
        if ($before->manifest->id !== $after->manifest->id
            || $before->manifest->version !== $after->manifest->version) {
            throw new \LogicException('Transition cannot change release identity.');
        }
        $this->database->transaction(function (Database $database) use ($before, $after, $makeActive, $clearActive): void {
            $current = $this->find($before->manifest->id, $before->manifest->version);
            if ($current === null || $current->state !== $before->state) {
                throw new \RuntimeException('Package release state changed concurrently.');
            }
            $this->save($after);
            if ($makeActive) {
                $this->setActiveInTransaction($database, $after->manifest->id, $after->manifest->version);
            } elseif ($clearActive) {
                $database->execute(new SqlStatement(sprintf(
                    'DELETE FROM %s WHERE package_id = :id AND version = :version',
                    $this->active,
                ), ['id' => $after->manifest->id, 'version' => $after->manifest->version]));
            }
        });
    }

    public function remove(string $packageId, string $version): void
    {
        $this->database->transaction(function (Database $database) use ($packageId, $version): void {
            $active = $database->fetchOne(new SqlStatement(sprintf(
                'SELECT version FROM %s WHERE package_id = :id', $this->active,
            ), ['id' => $packageId]));
            if ($active !== null && ($active['version'] ?? null) === $version) {
                throw new \LogicException('Active package release cannot be removed.');
            }
            $changed = $database->execute(new SqlStatement(sprintf(
                'DELETE FROM %s WHERE package_id = :id AND version = :version', $this->releases,
            ), ['id' => $packageId, 'version' => $version]));
            if ($changed !== 1) {
                throw new \LogicException('Package release is not registered.');
            }
        });
    }

    public function find(string $packageId, string $version): ?PackageRelease
    {
        $row = $this->database->fetchOne(new SqlStatement(sprintf(
            'SELECT manifest_json, release_path, state FROM %s WHERE package_id = :id AND version = :version',
            $this->releases,
        ), ['id' => $packageId, 'version' => $version]));
        return $row === null ? null : $this->hydrate($row);
    }

    public function releases(string $packageId): array
    {
        $rows = $this->database->fetchAll(new SqlStatement(sprintf(
            'SELECT manifest_json, release_path, state FROM %s WHERE package_id = :id',
            $this->releases,
        ), ['id' => $packageId]));
        $releases = array_map($this->hydrate(...), $rows);
        usort($releases, static fn (PackageRelease $a, PackageRelease $b): int =>
            version_compare($a->manifest->version, $b->manifest->version));
        return $releases;
    }

    public function allReleases(): array
    {
        $rows = $this->database->fetchAll(new SqlStatement(sprintf(
            'SELECT manifest_json, release_path, state FROM %s ORDER BY package_id, version',
            $this->releases,
        )));
        $releases = array_map($this->hydrate(...), $rows);
        usort($releases, static fn (PackageRelease $a, PackageRelease $b): int =>
            ($a->manifest->id <=> $b->manifest->id)
                ?: version_compare($a->manifest->version, $b->manifest->version));
        return $releases;
    }

    public function active(string $packageId): ?PackageRelease
    {
        $row = $this->database->fetchOne(new SqlStatement(sprintf(
            'SELECT releases.manifest_json, releases.release_path, releases.state FROM %s active '
            . 'JOIN %s releases ON releases.package_id = active.package_id AND releases.version = active.version '
            . 'WHERE active.package_id = :id',
            $this->active,
            $this->releases,
        ), ['id' => $packageId]));
        if ($row === null) {
            return null;
        }
        $release = $this->hydrate($row);
        if (!in_array($release->state, [PackageState::Active, PackageState::Degraded], true)) {
            throw new \RuntimeException('Active package pointer references a non-active release.');
        }
        return $release;
    }

    public function setActive(string $packageId, string $version): void
    {
        $this->database->transaction(function (Database $database) use ($packageId, $version): void {
            $this->setActiveInTransaction($database, $packageId, $version);
        });
    }

    private function setActiveInTransaction(Database $database, string $packageId, string $version): void
    {
        $release = $this->find($packageId, $version);
        if ($release === null || $release->state !== PackageState::Active) {
            throw new \LogicException('Only a registered active release may become the active pointer.');
        }
        $current = $database->fetchOne(new SqlStatement(sprintf(
            'SELECT version FROM %s WHERE package_id = :id',
            $this->active,
        ), ['id' => $packageId]));
        if ($current === null) {
            $database->execute(new SqlStatement(sprintf(
                'INSERT INTO %s (package_id, version) VALUES (:id, :version)',
                $this->active,
            ), ['id' => $packageId, 'version' => $version]));
            return;
        }
        $database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET version = :version WHERE package_id = :id',
            $this->active,
        ), ['id' => $packageId, 'version' => $version]));
    }

    public function clearActive(string $packageId): void
    {
        $this->database->execute(new SqlStatement(sprintf(
            'DELETE FROM %s WHERE package_id = :id',
            $this->active,
        ), ['id' => $packageId]));
    }

    /** @return array<string, string> */
    private function values(PackageRelease $release): array
    {
        $manifest = $release->manifest;
        return [
            'id' => $manifest->id,
            'version' => $manifest->version,
            'manifest' => json_encode([
                'schema' => $manifest->schema,
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'type' => $manifest->type->value,
                'requires' => [
                    'core' => $manifest->coreConstraint,
                    'capabilities' => (object) $manifest->requiredCapabilities,
                    'modules' => (object) $manifest->requiredModules,
                ],
                'provides' => ['capabilities' => (object) $manifest->providedCapabilities],
                'entrypoint' => $manifest->entrypoint,
            ], JSON_THROW_ON_ERROR),
            'path' => $release->releasePath,
            'state' => $release->state->value,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PackageRelease
    {
        $json = $row['manifest_json'] ?? null;
        $path = $row['release_path'] ?? null;
        $state = $row['state'] ?? null;
        if (!is_string($json) || !is_string($path) || !is_string($state)) {
            throw new \RuntimeException('Stored package release is malformed.');
        }
        return new PackageRelease($this->parser->parse($json), $path, PackageState::from($state));
    }
}
