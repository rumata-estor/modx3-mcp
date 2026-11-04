<?php
/**
 * Experimental, unconnected database-level compare-and-swap for nonstatic chunks.
 * NOT wired to the HTTP API, model dispatcher, or any live MODX site.
 *
 * Caller must independently authenticate a permit binding the exact before
 * and desired states, use a trusted MODX connection/table mapping and address
 * revision, cache, processor hooks and audit after the conditional update.
 * Never silently fall back to an ordinary MODX update processor on mismatch.
 */
class ModxMcpChunkCas
{
    public static function statement($table)
    {
        if (!is_string($table) ||
            !preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/D', $table)) {
            throw new InvalidArgumentException('trusted chunk table identifier required');
        }
        // BINARY comparisons matter: ordinary MySQL text collations can be
        // case-insensitive and ignore trailing spaces. No lost-update guard
        // may rely on ordinary `snippet = :before` comparisons.
        return 'UPDATE `' . $table . '` SET `snippet` = :after'
            . ' WHERE `id` = :id AND `static` = 0'
            . ' AND BINARY `snippet` = BINARY :before';
    }

    public static function compareAndSwap($connection, $table, $chunkId, $before, $after)
    {
        if (!is_object($connection) || !method_exists($connection, 'prepare') ||
            !is_int($chunkId) || $chunkId < 1 ||
            !is_string($before) || !is_string($after) ||
            hash_equals($before, $after)) {
            throw new InvalidArgumentException('invalid chunk CAS inputs');
        }
        $statement = $connection->prepare(self::statement($table));
        if (!is_object($statement) || !method_exists($statement, 'execute') ||
            !method_exists($statement, 'rowCount')) {
            throw new RuntimeException('chunk CAS statement unavailable');
        }
        if ($statement->execute(array(
            ':after' => $after, ':id' => $chunkId, ':before' => $before,
        )) !== true) {
            throw new RuntimeException('chunk CAS database update failed');
        }
        // 0 means a missing/static/changed row (or no change); never retry
        // with a refreshed baseline. >1 is an impossible primary key update.
        $changed = $statement->rowCount();
        if ($changed !== 1) {
            throw new RuntimeException('chunk CAS precondition failed or result ambiguous');
        }
        return true;
    }
}
