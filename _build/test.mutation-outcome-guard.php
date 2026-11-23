<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Негативные тесты остановки при неопределённом результате.
 * EN: Dependency-free tests for fail-closed mutation outcome reporting.
 */
class ModxMCPClientException extends RuntimeException {}
require_once __DIR__.'/../core/components/modxmcp/src/Security/MutationOutcomeGuard.php';

use ModxMcp\Security\MutationOutcomeGuard;

function outcomeCheck($value, $reason)
{
    if (!$value) throw new RuntimeException('MUTATION_OUTCOME_GUARD_FAIL: '.$reason);
}
$attempts = 0;
$result = MutationOutcomeGuard::run(function () use (&$attempts) {
    ++$attempts;
    return array('success'=>true, 'id'=>42);
});
outcomeCheck($result['id']===42 && $attempts===1, 'success once');

foreach (array(
    new RuntimeException('SECRET_NATIVE_PROCESSOR_PASSWORD'),
    new Error('SECRET_NATIVE_PHP_ERROR'),
    new ModxMCPClientException('INVALID_INPUT_AFTER_POSSIBLE_WRITE: SECRET'),
    new ModxMCPClientException('MUTATION_OUTCOME_UNCERTAIN: SECRET_RAW_EXCEPTION'),
) as $fault) {
    $before = $attempts;
    try {
        MutationOutcomeGuard::run(function () use (&$attempts, $fault) {
            ++$attempts;
            throw $fault;
        });
        throw new RuntimeException('Expected uncertain outcome was swallowed.');
    } catch (ModxMCPClientException $error) {
        $message = $error->getMessage();
        outcomeCheck(strpos($message, 'MUTATION_OUTCOME_UNCERTAIN:')===0, 'machine code');
        outcomeCheck(strpos($message, 'SECRET')===false, 'native exception redacted');
        outcomeCheck(strpos($message, 'stop further mutations')!==false, 'stop guidance');
        outcomeCheck(strpos($message, 'authorization')!==false, 'new decision guidance');
        outcomeCheck($error->getPrevious()===null, 'no native exception chained');
    }
    outcomeCheck($attempts===$before+1, 'never re-execute the operation');
}
echo 'MUTATION_OUTCOME_GUARD_OK attempts='.$attempts.PHP_EOL;
