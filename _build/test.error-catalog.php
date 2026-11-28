<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Поля диагностики, безопасные тексты и политика повторов.
 * EN: Error catalog safety, stable codes and conservative retry outcomes.
 */
require dirname(__DIR__) . '/core/components/modxmcp/src/Diagnostics/ErrorCatalog.php';
use ModxMcp\Diagnostics\ErrorCatalog;
function checkError($value, $label) {
    if (!$value) throw new RuntimeException('ERROR_CATALOG_FAIL ' . $label);
}
$cases = array(
    array('MEDIA_TPL_WRITE_REFUSED: unknown cause', 'MEDIA_TPL_WRITE_REFUSED', 'unknown', 'verify_before_retry'),
    array('VALIDATION_ERROR: invalid password=SUPER_SECRET', 'VALIDATION_ERROR', 'not_applied', 'after_client_fix'),
    array('MANUAL_REVIEW_REQUIRED: conflict unknown', 'MANUAL_REVIEW_REQUIRED', 'not_applied', 'after_review'),
    array('CAS_REQUIRED: revision required', 'CAS_REQUIRED', 'not_applied', 'after_refresh'),
    array('AUTH_DENIED: no permission', 'AUTH_DENIED', 'not_applied', 'after_authorization'),
    array('STALE_STATE: changed', 'STALE_STATE', 'not_applied', 'after_review'),
    array('MUTATION_OUTCOME_UNCERTAIN: failure', 'MUTATION_OUTCOME_UNCERTAIN', 'unknown', 'verify_before_retry'),
    array('SECURE_STATIC_IO_UNAVAILABLE: refuse', 'SECURE_STATIC_IO_UNAVAILABLE', 'unknown', 'after_admin_check'),
    array('Unexpected processor failed password=SUPER_SECRET', 'OPERATION_REFUSED', 'unknown', 'verify_before_retry'),
);
foreach ($cases as $row) {
    $fields = ErrorCatalog::fields($row[0]);
    checkError($fields['code'] === $row[1], $row[1] . ' code');
    checkError($fields['outcome'] === $row[2], $row[1] . ' outcome');
    checkError($fields['retry_policy'] === $row[3], $row[1] . ' retry');
    checkError((bool)preg_match('/^modxmcp_[a-f0-9]{24}$/', $fields['error_id']),
        $row[1] . ' identifier');
    checkError(strlen($fields['message_ru']) > 20
        && strlen($fields['reason_ru']) > 20
        && strlen($fields['next_action_ru']) > 20, $row[1] . ' explanation');
    checkError(strpos(json_encode($fields), 'SUPER_SECRET') === false,
        'raw exception must not leak into catalog');
}
checkError(ErrorCatalog::codeFromLegacy('CAS_CONFLICT: stale') === 'STALE_STATE',
    'CAS alias');
checkError(ErrorCatalog::codeFromLegacy('AUTH_DENIED: failed') === 'AUTH_DENIED',
    'auth alias');
checkError(ErrorCatalog::fields('anything', 'INTERNAL_ERROR', 'modxmcp_test')['outcome']
    === 'unknown', 'internal outcome');
// RU: Сбой журнала не должен скрывать исходную ошибку и рвать ответ.
class ThrowingDiagnosticLog {
    public function log($level, $line) { throw new RuntimeException('disk unavailable'); }
}
ErrorCatalog::safeLog(new ThrowingDiagnosticLog(), 3, 'MCP safe log');
echo 'ERROR_CATALOG_SAFE_RU_OK cases=' . count($cases) . PHP_EOL;
