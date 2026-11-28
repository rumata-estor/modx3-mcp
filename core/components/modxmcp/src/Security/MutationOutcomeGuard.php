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
final class PostCommitStepException extends \ModxMCPClientException {}

final class MutationOutcomeGuard
{
    const UNKNOWN_MESSAGE = 'MUTATION_OUTCOME_UNCERTAIN: the operation may have changed state; '
        . 'stop further mutations, inspect the affected objects through MCP, '
        . 'and obtain a fresh user decision and authorization before retrying.';

    // Fixed first-party diagnostic; preserve the existing uncertain machine code.
    const MEDIA_TPL_MESSAGE = 'MUTATION_OUTCOME_UNCERTAIN: MEDIA_TPL_WRITE_REFUSED: '
        . 'Media Source refused the .tpl write; check the target file before retrying. '
        . 'allowedFileTypes or MODX upload_files may restrict this extension; '
        . 'the actual cause has not been established. Ask an administrator to review policy.';

    const POST_COMMIT_MESSAGE = 'MUTATION_OUTCOME_UNCERTAIN: POST_COMMIT_STEP_FAILED: '
        . 'Database commit returned successfully, but a required follow-up step failed. '
        . 'Inspect persisted objects and cache state before any new mutation.';

    public static function postCommitFailure()
    {
        return new PostCommitStepException(self::POST_COMMIT_MESSAGE);
    }

    public static function uncertain($error = null)
    {
        if ($error instanceof PostCommitStepException
            && $error->getMessage() === self::POST_COMMIT_MESSAGE) {
            return self::postCommitFailure();
        }
        if ($error instanceof \ModxMCPClientException
            && $error->getMessage() === self::MEDIA_TPL_MESSAGE) {
            // Reconstruct from a constant; never return or chain the original exception.
            return new \ModxMCPClientException($error->getMessage());
        }
        // Never chain a native exception: processor errors may contain secrets.
        return new \ModxMCPClientException(self::UNKNOWN_MESSAGE);
    }

    /** Invoke exactly once. Any exception after dispatch starts means unknown. */
    public static function run(callable $operation)
    {
        try {
            return call_user_func($operation);
        } catch (\Throwable $error) {
            throw self::uncertain($error);
        }
    }
}
