<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Безопасный отказ после возможного побочного эффекта.
 * EN: Fail closed after a potentially applied mutation.
 */
namespace ModxMcp\Security;

/**
 * A successful CAS revision reservation does not prove that a subsequent
 * processor, file, plugin or external operation was rolled back on failure.
 * Do not leak a native exception or imply that nothing was applied.
 */
final class MutationOutcomeGuard
{
    const UNKNOWN_MESSAGE = 'MUTATION_OUTCOME_UNCERTAIN: the operation may have changed state; '
        . 'stop further mutations, inspect the affected objects through MCP, '
        . 'and obtain a fresh user decision and authorization before retrying.';

    public static function uncertain()
    {
        // Never chain a native exception: processor errors may contain secrets.
        return new \ModxMCPClientException(self::UNKNOWN_MESSAGE);
    }

    /** Invoke exactly once. Any exception after dispatch starts means unknown. */
    public static function run(callable $operation)
    {
        try {
            return call_user_func($operation);
        } catch (\Throwable $error) {
            throw self::uncertain();
        }
    }
}
