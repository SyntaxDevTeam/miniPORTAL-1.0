<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseWidgetPlacementRepository implements WidgetPlacementRepository
{
    private string $table;

    public function __construct(private Database $database)
    {
        $this->table = (new StorageNamespace(DatabaseWidgetMigration::OWNER_ID))->table('placements')->value;
    }

    public function forSlot(string $pageId, string $slot): array
    {
        $rows = $this->database->fetchAll(new SqlStatement(sprintf(
            'SELECT id, page_id, slot_id, module_id, widget_type, position, config_json, required_permission '
            . 'FROM %s WHERE page_id = :page AND slot_id = :slot ORDER BY position, id', $this->table,
        ), ['page' => $pageId, 'slot' => $slot]));
        $instances = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $page = $row['page_id'] ?? null;
            $slotId = $row['slot_id'] ?? null;
            $module = $row['module_id'] ?? null;
            $type = $row['widget_type'] ?? null;
            $position = $row['position'] ?? null;
            $json = $row['config_json'] ?? null;
            $permission = $row['required_permission'] ?? null;
            if (!is_string($id) || !is_string($page) || !is_string($slotId) || !is_string($module)
                || !is_string($type) || !is_numeric($position) || !is_string($json)
                || ($permission !== null && !is_string($permission))) {
                throw new \RuntimeException('Stored widget placement is malformed.');
            }
            $config = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
            if (!$config instanceof \stdClass) {
                throw new \RuntimeException('Stored widget configuration is malformed.');
            }
            $normalized = [];
            foreach (get_object_vars($config) as $key => $value) {
                if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                    throw new \RuntimeException('Stored widget configuration is malformed.');
                }
                $normalized[$key] = $value;
            }
            $instances[] = new WidgetInstance($id, $page, $slotId, $module, $type,
                (int) $position, $normalized, $permission);
        }
        return $instances;
    }

    public function save(WidgetInstance $instance): void
    {
        $values = [
            'id' => $instance->id,
            'page' => $instance->pageId,
            'slot' => $instance->slot,
            'module' => $instance->moduleId,
            'type' => $instance->type,
            'position' => $instance->position,
            'config' => json_encode((object) $instance->configuration, JSON_THROW_ON_ERROR),
            'permission' => $instance->requiredPermission,
        ];
        $this->database->transaction(function (Database $db) use ($values): void {
            $existing = $db->fetchOne(new SqlStatement(sprintf('SELECT id FROM %s WHERE id = :id', $this->table),
                ['id' => $values['id']]));
            if ($existing === null) {
                $db->execute(new SqlStatement(sprintf('INSERT INTO %s '
                    . '(id, page_id, slot_id, module_id, widget_type, position, config_json, required_permission) '
                    . 'VALUES (:id, :page, :slot, :module, :type, :position, :config, :permission)', $this->table), $values));
            } else {
                $db->execute(new SqlStatement(sprintf('UPDATE %s SET page_id = :page, slot_id = :slot, '
                    . 'module_id = :module, widget_type = :type, position = :position, config_json = :config, '
                    . 'required_permission = :permission WHERE id = :id', $this->table), $values));
            }
        });
    }

    public function remove(string $id): void
    {
        $this->database->execute(new SqlStatement(sprintf('DELETE FROM %s WHERE id = :id', $this->table), ['id' => $id]));
    }
}
