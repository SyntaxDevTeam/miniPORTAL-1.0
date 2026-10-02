<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\SitePages;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;

final readonly class SitePageRepository
{
    private string $table;

    public function __construct(private Database $database)
    {
        $this->table = (new StorageNamespace('site.pages'))->table('pages')->value;
    }

    public static function migration(): MigrationDefinition
    {
        $table = (new StorageNamespace('site.pages'))->table('pages')->value;
        return new MigrationDefinition('site.pages', '001-create-pages',
            new MigrationMetadata(null, '1', 'Store optional site pages'),
            [new SqlStatement('CREATE TABLE ' . $table . ' ('
                . 'id VARCHAR(64) PRIMARY KEY, slug VARCHAR(191) NOT NULL UNIQUE, '
                . 'title VARCHAR(180) NOT NULL, summary TEXT NOT NULL, content TEXT NOT NULL, '
                . 'content_format VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, '
                . 'author_id VARCHAR(64) NOT NULL, legacy_id BIGINT NULL UNIQUE, '
                . 'created_at VARCHAR(32) NOT NULL, updated_at VARCHAR(32) NOT NULL)')],
            [new SqlStatement('DROP TABLE ' . $table)]);
    }

    /** @return list<SitePage> */
    public function listing(bool $publishedOnly = true): array
    {
        $sql = 'SELECT id, slug, title, summary, content, content_format, status, author_id, legacy_id '
            . 'FROM ' . $this->table;
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $sql .= ' ORDER BY title ASC LIMIT 100';
        return array_map($this->hydrate(...), $this->database->fetchAll(new SqlStatement($sql)));
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?SitePage
    {
        $sql = 'SELECT id, slug, title, summary, content, content_format, status, author_id, legacy_id '
            . 'FROM ' . $this->table . ' WHERE slug = :slug';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $row = $this->database->fetchOne(new SqlStatement($sql, ['slug' => $slug]));
        return $row === null ? null : $this->hydrate($row);
    }

    public function findById(string $id): ?SitePage
    {
        $row = $this->database->fetchOne(new SqlStatement(
            'SELECT id, slug, title, summary, content, content_format, status, author_id, legacy_id '
            . 'FROM ' . $this->table . ' WHERE id = :id', ['id' => $id]));
        return $row === null ? null : $this->hydrate($row);
    }

    public function save(SitePage $page): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $values = [
            'id' => $page->id, 'slug' => $page->slug, 'title' => $page->title,
            'summary' => $page->summary, 'content' => $page->content,
            'format' => $page->format->value, 'status' => $page->status,
            'author_id' => $page->authorId, 'legacy_id' => $page->legacyId,
            'updated_at' => $now,
        ];
        if ($this->findById($page->id) === null) {
            $this->database->execute(new SqlStatement('INSERT INTO ' . $this->table
                . ' (id, slug, title, summary, content, content_format, status, author_id, legacy_id, created_at, updated_at) '
                . 'VALUES (:id, :slug, :title, :summary, :content, :format, :status, :author_id, :legacy_id, :created_at, :updated_at)',
                [...$values, 'created_at' => $now]));
        } else {
            $this->database->execute(new SqlStatement('UPDATE ' . $this->table
                . ' SET slug = :slug, title = :title, summary = :summary, content = :content, '
                . 'content_format = :format, status = :status, author_id = :author_id, '
                . 'updated_at = :updated_at WHERE id = :id',
                array_diff_key($values, ['legacy_id' => true])));
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SitePage
    {
        $legacyId = $row['legacy_id'] ?? null;
        return new SitePage(
            $this->string($row, 'id'), $this->string($row, 'slug'), $this->string($row, 'title'),
            $this->string($row, 'summary'), $this->string($row, 'content'),
            ContentFormat::from($this->string($row, 'content_format')),
            $this->string($row, 'status'), $this->string($row, 'author_id'),
            $legacyId === null ? null : $this->integer($legacyId),
        );
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Page column %s must be a string.', $key));
        }
        return $value;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1) {
            return (int) $value;
        }
        throw new \UnexpectedValueException('Legacy page ID must be an integer.');
    }
}
