<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Экспериментальный журнал намерений мутаций с неизменяемым идентификатором.
 * EN: Experimental durable mutation-intent ledger with immutable operation ID.
 */
namespace ModxMcp\Security;

/**
 * RU: Журнал подключён к диспетчеру через MutationOperationGate,
 * RU: но работает только при явном включении protected-mode, подписанного
 * RU: operation_id и существующей отдельной таблицы журнала.
 * EN: Wired into the dispatcher via MutationOperationGate; opt-in only.
 * EN: Requires protected signed authorization, an operation ID and its own
 * EN: provisioned ledger table. This does NOT make arbitrary external
 * EN: processors or package installers transactional/idempotent.
 * Claim is durable before side effects on an independent autocommit PDO.
 * Duplicate claims are never automatically dispatched again.
 */
final class MutationOperationLedger
{
    private $pdo;
    private $table;

    public function __construct(\PDO $pdo, $table)
    {
        if (!is_string($table)
            || !preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $table)) {
            throw new \InvalidArgumentException('Invalid operation journal table name.');
        }
        $this->pdo = $pdo;
        $this->table = '`' . $table . '`';
    }

    /**
     * Bind intent to the complete normalized request (including CAS and site).
     * Never store the original request, signed permit, access tokens or result.
     */
    public static function requestDigest($site, $action, $elementType, array $data)
    {
        if (!is_string($site) || $site === '' || !is_string($action)
            || $action === '' || !is_string($elementType)) {
            throw new \InvalidArgumentException('Operation scope is required.');
        }
        $normalized = self::canonicalize($data);
        $encoded = json_encode(array($site, $action, $elementType, $normalized),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException('Operation request cannot be encoded.');
        }
        return hash('sha256', "MODXMCP-OPERATION-REQUEST-V1\n" . $encoded);
    }

    private static function canonicalize(array $values)
    {
        if (array_keys($values) !== range(0, count($values) - 1)) {
            ksort($values, SORT_STRING);
        }
        foreach ($values as $k => $value) {
            if (is_array($value)) $values[$k] = self::canonicalize($value);
            elseif (is_object($value) || is_resource($value)) {
                throw new \InvalidArgumentException('Invalid operation input.');
            }
        }
        return $values;
    }

    private static function validate($operationId, $digest)
    {
        if (!is_string($operationId)
            || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $operationId)
            || !is_string($digest)
            || !preg_match('/\A[0-9a-f]{64}\z/D', $digest)) {
            throw new \InvalidArgumentException('Invalid idempotency identifier or digest.');
        }
    }

    /** Claim before mutation; replay always returns status, never execute. */
    public function claim($operationId, $digest, $now = null)
    {
        self::validate($operationId, $digest);
        if ($this->pdo->inTransaction()) {
            throw new \RuntimeException('Operation journal must be outside mutation transaction.');
        }
        $time = $now === null ? time() : $now;
        if (!is_int($time) || $time < 1) throw new \InvalidArgumentException('Invalid time.');
        $opHash = hash('sha256', "MODXMCP-OPERATION-ID-V1\n".$operationId);
        try {
            $statement = $this->pdo->prepare('INSERT INTO '.$this->table
                .' (operation_hash, request_hash, state, created_at, updated_at)'
                .' VALUES (?, ?, ?, ?, ?)');
            if (!$statement || !$statement->execute(array(
                $opHash,$digest,'PENDING',$time,$time))) {
                throw new \RuntimeException('Reservation failed.');
            }
            return array('fresh'=>true,'state'=>'PENDING');
        } catch (\PDOException $error) {
            // Only an actual duplicate-key may fall through to readback.
            // Any other database failure must stop the mutation.
            $sqlState = isset($error->errorInfo[0]) ? (string)$error->errorInfo[0] : '';
            $driverCode = isset($error->errorInfo[1]) ? (int)$error->errorInfo[1] : 0;
            if ($sqlState !== '23000' || $driverCode !== 1062) {
                throw new \RuntimeException('Operation journal unavailable.',0,$error);
            }
            $existing = $this->get($operationId, $digest);
            return array('fresh'=>false,'state'=>$existing['state']);
        } catch (\Throwable $error) {
            throw new \RuntimeException('Operation journal unavailable.',0,$error);
        }
    }

    /** Read only status; never replay cached payloads or raw processor output. */
    public function get($operationId, $digest)
    {
        self::validate($operationId, $digest);
        $opHash = hash('sha256', "MODXMCP-OPERATION-ID-V1\n".$operationId);
        try {
            $statement = $this->pdo->prepare('SELECT request_hash,state FROM '
                .$this->table.' WHERE operation_hash=?');
            if (!$statement || !$statement->execute(array($opHash))) {
                throw new \RuntimeException('Readback failed.');
            }
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) throw new \RuntimeException('Unknown operation ID.');
            if (!hash_equals((string)$row['request_hash'],$digest)) {
                throw new \RuntimeException('Operation ID bound to another request.');
            }
            if (!in_array($row['state'],array('PENDING','SUCCEEDED','UNCERTAIN'),true)) {
                throw new \RuntimeException('Invalid journal state.');
            }
            return array('state'=>$row['state']);
        } catch (\Throwable $error) {
            throw new \RuntimeException('Operation status unavailable or request mismatch.',0,$error);
        }
    }

    /**
     * Terminal transitions are monotonic. A pending operation after PHP crash
     * stays PENDING, never becomes automatically retryable.
     */
    public function finish($operationId,$digest,$state,$now = null)
    {
        self::validate($operationId,$digest);
        if (!in_array($state,array('SUCCEEDED','UNCERTAIN'),true)) {
            throw new \InvalidArgumentException('Unsupported operation terminal state.');
        }
        if ($this->pdo->inTransaction()) {
            throw new \RuntimeException('Operation journal requires autocommit.');
        }
        $time = $now === null ? time() : $now;
        if (!is_int($time) || $time < 1) throw new \InvalidArgumentException('Invalid time.');
        $opHash=hash('sha256',"MODXMCP-OPERATION-ID-V1\n".$operationId);
        try {
            $statement=$this->pdo->prepare('UPDATE '.$this->table
                .' SET state=?,updated_at=? WHERE operation_hash=?'
                .' AND request_hash=? AND state=?');
            if (!$statement || !$statement->execute(array(
                $state,$time,$opHash,$digest,'PENDING'))) {
                throw new \RuntimeException('Status change failed.');
            }
            if ($statement->rowCount()!==1) {
                throw new \RuntimeException('Operation missing, conflicting or terminal.');
            }
        } catch (\Throwable $error) {
            throw new \RuntimeException('Operation completion unavailable.',0,$error);
        }
    }
}
