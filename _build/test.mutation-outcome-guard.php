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
// Verify both distributed MODX 2/3 dispatchers use the same safe boundary
// for non-atomic effects and work performed after a confirmed DB commit.
foreach (array(
    __DIR__.'/../core/components/modxmcp/model/modxmcp.class.php',
    __DIR__.'/../core/components/modxmcp/legacy/modx2/modxmcp.class.php',
) as $dispatcher) {
    $source = file_get_contents($dispatcher);
    outcomeCheck(is_string($source), 'dispatcher source present');
    outcomeCheck(substr_count($source,
        '\\ModxMcp\\Security\\MutationOutcomeGuard::run(')===2,
        'non-atomic and post-commit boundaries wired: '.basename($dispatcher));
}
$gate = file_get_contents(__DIR__.'/../core/components/modxmcp/src/Security/MutationOperationGate.php');
outcomeCheck(strpos($gate, 'throw MutationOutcomeGuard::uncertain();')!==false,
    'signed operation journal also fails closed after a thrown operation');

echo 'MUTATION_OUTCOME_GUARD_OK attempts='.$attempts.PHP_EOL;
