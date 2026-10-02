<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Migration\Legacy;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

/** Checksum-bound, one-time import into the optional site.pages schema. */
final readonly class PagesImporter
{
    private string $pages;
    private string $users;

    public function __construct(private Database $database)
    {
        $this->pages = (new StorageNamespace('site.pages'))->table('pages')->value;
        $this->users = (new StorageNamespace(DatabaseIdentityMigration::OWNER_ID))->table('users')->value;
    }

    public function plan(string $snapshot): PagesImportPlan
    {
        $rows = $this->parse($snapshot);
        $this->assertTargetReady($this->database, $rows);
        return $this->describe($snapshot, $rows);
    }

    public function apply(string $snapshot, string $expectedChecksum): PagesImportPlan
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedChecksum) !== 1
            || !hash_equals($expectedChecksum, hash('sha256', $snapshot))) {
            throw new \LogicException('Legacy pages snapshot differs from the reviewed plan.');
        }
        $rows = $this->parse($snapshot);
        $plan = $this->describe($snapshot, $rows);
        $this->database->transaction(function (Database $db) use ($rows): void {
            $this->assertTargetReady($db, $rows);
            foreach ($rows as $row) {
                $db->execute(new SqlStatement('INSERT INTO ' . $this->pages
                    . ' (id, slug, title, summary, content, content_format, status, author_id, '
                    . 'legacy_id, created_at, updated_at) VALUES '
                    . '(:id, :slug, :title, :summary, :content, :format, :status, :author_id, '
                    . ':legacy_id, :created_at, :updated_at)', [
                        'id' => 'legacy-' . $row['id'], 'slug' => $row['slug'], 'title' => $row['title'],
                        'summary' => $row['summary'], 'content' => $row['content'],
                        'format' => $row['content_format'], 'status' => $row['status'],
                        'author_id' => IdentityImporter::newId($row['author_id']),
                        'legacy_id' => $row['id'], 'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ]));
            }
        });
        return $plan;
    }

    /** @param list<array{id:int,title:string,slug:string,summary:string,content:string,content_format:string,status:string,author_id:int,created_at:string,updated_at:string}> $rows */
    private function describe(string $snapshot, array $rows): PagesImportPlan
    {
        return new PagesImportPlan(hash('sha256', $snapshot), count($rows),
            count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'published')));
    }

    /**
     * @return list<array{id:int,title:string,slug:string,summary:string,content:string,content_format:string,status:string,author_id:int,created_at:string,updated_at:string}>
     */
    private function parse(string $snapshot): array
    {
        if ($snapshot === '' || strlen($snapshot) > 10_000_000) {
            throw new \InvalidArgumentException('Legacy pages snapshot is empty or exceeds 10 MB.');
        }
        try {
            $raw = json_decode($snapshot, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Legacy pages snapshot is invalid JSON.', previous: $exception);
        }
        if (!is_array($raw) || array_is_list($raw) || ($raw['schema'] ?? null) !== 1
            || !is_array($raw['pages'] ?? null) || !array_is_list($raw['pages'])) {
            throw new \InvalidArgumentException('Legacy pages snapshot schema is unsupported.');
        }
        $timezone = $raw['timezone'] ?? null;
        if (!is_string($timezone) || strlen($timezone) > 64) {
            throw new \InvalidArgumentException('Legacy pages timezone is invalid.');
        }
        $zone = new DateTimeZone($timezone);
        $rows = [];
        $ids = [];
        $slugs = [];
        foreach ($raw['pages'] as $source) {
            if (!is_array($source)) {
                throw new \InvalidArgumentException('Legacy page row is invalid.');
            }
            $id = $this->positiveInt($source['id'] ?? null, 'page ID');
            $authorId = $this->positiveInt($source['author_id'] ?? null, 'author ID');
            $title = $this->string($source['title'] ?? null, 'title', 180);
            $slug = $this->string($source['slug'] ?? null, 'slug', 191);
            $summary = $this->string($source['summary'] ?? null, 'summary', 2000, true);
            $content = $this->string($source['content'] ?? null, 'content', 500_000);
            $format = $this->string($source['content_format'] ?? null, 'content format', 20);
            $status = $this->string($source['status'] ?? null, 'status', 20);
            if (isset($ids[$id]) || isset($slugs[$slug])
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
                || !in_array($format, ['html', 'markdown'], true)
                || !in_array($status, ['draft', 'published'], true)) {
                throw new \InvalidArgumentException('Legacy page has duplicate or unsupported fields.');
            }
            $ids[$id] = true;
            $slugs[$slug] = true;
            $rows[] = [
                'id' => $id, 'title' => $title, 'slug' => $slug, 'summary' => $summary,
                'content' => $content, 'content_format' => $format, 'status' => $status,
                'author_id' => $authorId,
                'created_at' => $this->timestamp($source['created_at'] ?? null, $zone),
                'updated_at' => $this->timestamp($source['updated_at'] ?? null, $zone),
            ];
        }
        if ($rows === []) {
            throw new \InvalidArgumentException('Legacy pages snapshot has no pages.');
        }
        return $rows;
    }

    /** @param list<array{author_id:int}> $rows */
    private function assertTargetReady(Database $db, array $rows): void
    {
        $existing = $db->fetchOne(new SqlStatement('SELECT id FROM ' . $this->pages . ' LIMIT 1'));
        if ($existing !== null) {
            throw new \LogicException('Target pages table is not empty.');
        }
        foreach ($rows as $row) {
            $userId = IdentityImporter::newId($row['author_id']);
            if ($db->fetchOne(new SqlStatement('SELECT id FROM ' . $this->users . ' WHERE id = :id',
                ['id' => $userId])) === null) {
                throw new \LogicException('Legacy page author is missing from imported identities.');
            }
        }
    }

    private function positiveInt(mixed $value, string $label): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            return (int) $value;
        }
        throw new \InvalidArgumentException('Legacy ' . $label . ' is invalid.');
    }

    private function string(mixed $value, string $label, int $max, bool $allowEmpty = false): string
    {
        if (!is_string($value) || (!$allowEmpty && trim($value) === '') || mb_strlen($value) > $max) {
            throw new \InvalidArgumentException('Legacy page ' . $label . ' is invalid.');
        }
        return $value;
    }

    private function timestamp(mixed $value, DateTimeZone $zone): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Legacy page timestamp is invalid.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
        if ($parsed === false || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new \InvalidArgumentException('Legacy page timestamp is invalid.');
        }
        return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
