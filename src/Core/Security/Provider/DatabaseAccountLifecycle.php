<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use Throwable;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountLifecycleDenied;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountNotFound;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\AccountLifecycle;
use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseAccountLifecycle implements AccountLifecycle
{
    private string $users;
    private string $userRoles;
    private string $bootstrap;

    public function __construct(
        private Database $database,
        private AuditTrail $audit,
    ) {
        $namespace = new StorageNamespace(DatabaseIdentityMigration::OWNER_ID);
        $this->users = $namespace->table('users')->value;
        $this->userRoles = $namespace->table('user_roles')->value;
        $this->bootstrap = $namespace->table('bootstrap')->value;
    }

    public function changeStatus(
        string $accountId,
        AccountStatus $status,
        string $actor,
        string $correlationId,
    ): void {
        if (preg_match('/^[a-f0-9]{32}$/D', $accountId) !== 1) {
            throw new \InvalidArgumentException('Account ID is invalid.');
        }

        $previousStatus = null;
        try {
            $previousStatus = $this->database->transaction(function (Database $database) use ($accountId, $status, &$previousStatus): AccountStatus {
                $database->execute(new SqlStatement(sprintf(
                    'UPDATE %s SET singleton_id = singleton_id WHERE singleton_id = 1',
                    $this->bootstrap,
                )));

                $row = $database->fetchOne(new SqlStatement(sprintf(
                    'SELECT status FROM %s WHERE id = :id',
                    $this->users,
                ), ['id' => $accountId]));
                if ($row === null) {
                    throw new AccountNotFound('Account does not exist.');
                }
                $current = AccountStatus::from($this->requiredString($row, 'status'));
                $previousStatus = $current;
                if ($current === $status) {
                    throw new AccountLifecycleDenied('Account already has the requested status.');
                }
                if ($status === AccountStatus::Pending) {
                    throw new AccountLifecycleDenied('An existing account cannot return to pending status.');
                }

                if ($status !== AccountStatus::Active && $this->hasOwnerRole($database, $accountId)) {
                    $activeOwners = $database->fetchOne(new SqlStatement(sprintf(
                        "SELECT COUNT(*) AS aggregate_count FROM %s users JOIN %s user_roles "
                        . "ON user_roles.user_id = users.id WHERE user_roles.role_name = 'owner' AND users.status = 'active'",
                        $this->users,
                        $this->userRoles,
                    )));
                    $count = $this->requiredCount($activeOwners, 'aggregate_count');
                    if ($count <= 1) {
                        throw new AccountLifecycleDenied('The last active Owner account cannot be blocked.');
                    }
                }

                $changed = $database->execute(new SqlStatement(sprintf(
                    'UPDATE %s SET status = :status WHERE id = :id AND status = :current_status',
                    $this->users,
                ), [
                    'status' => $status->value,
                    'id' => $accountId,
                    'current_status' => $current->value,
                ]));
                if ($changed !== 1) {
                    throw new \RuntimeException('Account status changed concurrently.');
                }

                return $current;
            });
        } catch (AccountLifecycleDenied|AccountNotFound $exception) {
            $this->record($actor, $accountId, AuditResult::Denied, $correlationId, $previousStatus, $status);
            throw $exception;
        } catch (Throwable $exception) {
            $this->record($actor, $accountId, AuditResult::Failed, $correlationId, $previousStatus, $status);
            throw $exception;
        }

        $this->record($actor, $accountId, AuditResult::Succeeded, $correlationId, $previousStatus, $status);
    }

    private function hasOwnerRole(Database $database, string $accountId): bool
    {
        return $database->fetchOne(new SqlStatement(sprintf(
            "SELECT role_name FROM %s WHERE user_id = :user_id AND role_name = 'owner'",
            $this->userRoles,
        ), ['user_id' => $accountId])) !== null;
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Account row field %s is invalid.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed>|null $row */
    private function requiredCount(?array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        throw new \RuntimeException(sprintf('Aggregate row field %s is invalid.', $key));
    }

    private function record(
        string $actor,
        string $accountId,
        AuditResult $result,
        string $correlationId,
        ?AccountStatus $from,
        AccountStatus $to,
    ): void {
        $this->audit->record(
            $actor,
            'account.status.change',
            'account:' . $accountId,
            $result,
            $correlationId,
            ['from' => $from?->value, 'to' => $to->value],
        );
    }
}
