<?php
/**
 * Durable, single-use CAS nonce ledger backed by preprovisioned InnoDB.
 *
 * Source-only: not attached to HTTP. Requires an independent trusted PDO
 * connection, no active transaction, autocommit and a pre-created table with
 * nonce_sha256 CHAR(64) PRIMARY KEY. A reservation must commit BEFORE the
 * separate guarded MODX write transaction, including on conflicts/timeouts.
 * Only the nonce's SHA-256 digest is stored (not the permit or CMS content).
 */
require_once __DIR__.'/permit-nonce-ledger.php';

class ModxMcpPermitNonceLedgerDb extends ModxMcpPermitNonceLedger
{
    private $db;
    private $table;

    public function __construct($db, $table)
    {
        if (!($db instanceof PDO) ||
            !is_string($table) ||
            !preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/D', $table)) {
            throw new InvalidArgumentException('trusted nonce database and table required');
        }
        if ($db->inTransaction()) {
            throw new RuntimeException('nonce ledger connection must not be in a transaction');
        }
        try {
            $session = $db->query('SELECT @@session.autocommit');
            if (!$session || (int)$session->fetchColumn() !== 1) {
                throw new RuntimeException('nonce ledger requires autocommit');
            }
            $schema = $db->prepare(
                'SELECT ENGINE FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            if (!$schema || $schema->execute(array($table)) !== true ||
                strcasecmp((string)$schema->fetchColumn(), 'InnoDB') !== 0) {
                throw new RuntimeException('nonce ledger requires preprovisioned InnoDB table');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('trusted nonce ledger database unavailable', 0, $e);
        }
        $this->db = $db;
        $this->table = $table;
    }

    public function reserve($nonce)
    {
        if (!is_string($nonce) || !preg_match('/\A[0-9a-f]{32}\z/D', $nonce)) {
            throw new InvalidArgumentException('invalid permit nonce');
        }
        if ($this->db->inTransaction()) {
            throw new RuntimeException('cannot reserve nonce in a MODX transaction');
        }
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `'.$this->table.'` (nonce_sha256) VALUES (?)'
            );
            if (!$stmt || $stmt->execute(array(hash('sha256', $nonce))) !== true ||
                $stmt->rowCount() !== 1) {
                throw new RuntimeException('nonce reservation rejected');
            }
        } catch (PDOException $e) {
            // Duplicate key and unavailable database both DENY; never expose
            // SQL errors, raw nonce, credentials or a retryable grant.
            throw new RuntimeException('nonce already used or ledger unavailable', 0, $e);
        }
        return true;
    }
}
