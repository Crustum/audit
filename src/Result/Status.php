<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

/**
 * Diagnostic and fix result statuses.
 */
enum Status: string
{
    case Pass = 'pass';
    case Notice = 'notice';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';
    case Error = 'error';

    /**
     * Determine whether the status represents a failure.
     *
     * @return bool
     */
    public function failed(): bool
    {
        return $this === self::Fail || $this === self::Error;
    }
}
