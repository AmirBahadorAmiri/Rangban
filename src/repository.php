<?php
/**
 * عملیات CRUD روی پالت‌ها و رنگ‌ها
 */

declare(strict_types=1);

function palette_all(): array
{
    return db()->query(
        'SELECT p.*, COUNT(c.id) AS color_count
           FROM palettes p
           LEFT JOIN colors c ON c.palette_id = p.id
          GROUP BY p.id
          ORDER BY p.last_used_at IS NULL, p.last_used_at DESC, p.updated_at DESC'
    )->fetchAll();
}

function palette_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM palettes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function palette_create(string $name, ?string $description): int
{
    $stmt = db()->prepare('INSERT INTO palettes (name, description) VALUES (?, ?)');
    $stmt->execute([trim($name), $description !== null && trim($description) !== '' ? trim($description) : null]);
    return (int) db()->lastInsertId();
}

function palette_update(int $id, string $name, ?string $description): bool
{
    $stmt = db()->prepare('UPDATE palettes SET name = ?, description = ? WHERE id = ?');
    return $stmt->execute([trim($name), $description !== null && trim($description) !== '' ? trim($description) : null, $id]);
}

function palette_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM palettes WHERE id = ?');
    return $stmt->execute([$id]);
}

function palette_touch(int $id): void
{
    db()->prepare('UPDATE palettes SET last_used_at = NOW() WHERE id = ?')->execute([$id]);
}

function colors_of_palette(int $paletteId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM colors WHERE palette_id = ? ORDER BY position ASC, id ASC'
    );
    $stmt->execute([$paletteId]);
    return $stmt->fetchAll();
}

function color_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM colors WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function color_create(int $paletteId, string $hex, ?string $name, bool $favorite = false): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM colors WHERE palette_id = ?');
    $stmt->execute([$paletteId]);
    $pos = (int) $stmt->fetchColumn();

    $ins = db()->prepare('INSERT INTO colors (palette_id, hex_code, name, position, is_favorite) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$paletteId, $hex, $name !== null && trim($name) !== '' ? trim($name) : null, $pos, $favorite ? 1 : 0]);
    return (int) db()->lastInsertId();
}

function color_update(int $id, string $hex, ?string $name): bool
{
    $stmt = db()->prepare('UPDATE colors SET hex_code = ?, name = ? WHERE id = ?');
    return $stmt->execute([$hex, $name !== null && trim($name) !== '' ? trim($name) : null, $id]);
}

/** تغییر کد رنگ بدون دست‌زدن به نام و ستاره */
function color_set_hex(int $id, string $hex): bool
{
    $stmt = db()->prepare('UPDATE colors SET hex_code = ? WHERE id = ?');
    return $stmt->execute([$hex, $id]);
}

function color_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM colors WHERE id = ?');
    return $stmt->execute([$id]);
}

function color_toggle_favorite(int $id): bool
{
    $stmt = db()->prepare('UPDATE colors SET is_favorite = 1 - is_favorite WHERE id = ?');
    return $stmt->execute([$id]);
}

/** جابه‌جایی ترتیب رنگ‌ها با drag & drop — آرایه‌ی ID به ترتیب جدید */
function color_reorder(int $paletteId, array $orderedIds): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE colors SET position = ? WHERE id = ? AND palette_id = ?');
        foreach (array_values($orderedIds) as $i => $cid) {
            $stmt->execute([$i, (int) $cid, $paletteId]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}
