<?php
namespace ModxMcp\Security;

/**
 * Standalone nonce-consumption primitive. Not wired to the MCP endpoint.
 *
 * Requires a separately provisioned InnoDB table with nonce_hash CHAR(64)
 * PRIMARY KEY, issued_at BIGINT NOT NULL, expires_at BIGINT NOT NULL.
 *
 * Must be invoked after signature + request binding checks, and BEFORE
 * executing a mutation. The supplied PDO must be a DEDICATED connection,
 * in autocommit mode and outside any transaction used for the mutation.
 * A rejected, failed or unknown mutation NEVER releases a nonce.
 */
final class PermitNonceLedger
{
    private $pdo;
    private $table;

    public function __construct(\PDO $connection, $table)
    {
        if (!is_string($table)
            || !preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $table)) {
            throw new \InvalidArgumentException('Invalid nonce ledger table name.');
        }
        $this->pdo = $connection;
        $this->table = '`' . $table . '`';
    }

    /**
     * One successful, permanently committed reservation, or an exception.
     * DB duplicate-KEY constraints provide atomic replay rejection across
     * independent PHP workers and separate web servers using one database.
     */
    public function reserve($nonce, $issuedAt, $expiresAt, $now = null)
    {
        if (!is_string($nonce) || !preg_match('/\A[0-9a-f]{32}\z/D', $nonce)) {
            throw new \InvalidArgumentException('Invalid replay nonce.');
        }
        if (!is_int($issuedAt) || !is_int($expiresAt)
            || $issuedAt <= 0 || $expiresAt <= $issuedAt) {
            throw new \InvalidArgumentException('Invalid replay nonce times.');
        }
        $time = $now === null ? time() : $now;
        if (!is_int($time) || $time <= 0
            || $issuedAt > $time + 5 || $issuedAt < $time - 120
            || $expiresAt <= $time || $expiresAt > $time + 120) {
            throw new \RuntimeException('Refusing expired or unbounded replay nonce.');
        }
        // Autocommit is essential: a transaction used for the actual MODX
        // mutation may roll back, but the consumed nonce must survive.
        if ($this->pdo->inTransaction()) {
            throw new \RuntimeException('Replay ledger cannot share a mutation transaction.');
        }
        $hash = hash('sha256', "MODXMCP-ED25519-NONCE-V1\n" . $nonce);
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO ' . $this->table
                . ' (`nonce_hash`, `issued_at`, `expires_at`)'
                . ' VALUES (?, ?, ?)'
            );
            if (!$statement || !$statement->execute(array(
                $hash, $issuedAt, $expiresAt
            )) || $statement->rowCount() !== 1) {
                throw new \RuntimeException('Nonce reservation did not complete.');
            }
        } catch (\Throwable $e) {
            // Never expose SQL/DB error text; duplicate and storage failure
            // both fail closed.
            throw new \RuntimeException('Permit replayed or nonce ledger unavailable.',0,$e);
        }
        // Do NOT delete the entry, even if the subsequent mutation fails.
        // Retention can be managed by a separate, secure expiry migration.
        return true;
    }
}
