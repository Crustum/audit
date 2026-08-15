<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

/**
 * Formatting helpers for diagnostic evidence.
 */
final class Details
{
    /**
     * Format strings as a bullet list.
     *
     * @param list<string> $items Items to format.
     * @return string
     */
    public static function bullets(array $items): string
    {
        return implode(
            PHP_EOL,
            array_map(
                static fn(string $item): string => str_starts_with($item, '- ') ? $item : '- ' . $item,
                $items,
            ),
        );
    }

    /**
     * Format keyed failure messages.
     *
     * @param array<string, string> $failures Failure messages keyed by name.
     * @return string
     */
    public static function failures(array $failures): string
    {
        $formatted = [];

        foreach ($failures as $name => $message) {
            $formatted[] = sprintf('- %s: %s', $name, $message);
        }

        return implode(PHP_EOL, $formatted);
    }

    /**
     * Get the most useful process output.
     *
     * @param string $output Standard output.
     * @param string $errorOutput Standard error output.
     * @param string $fallback Fallback when both streams are blank.
     * @return string
     */
    public static function processOutput(string $output, string $errorOutput, string $fallback): string
    {
        $selectedOutput = trim($errorOutput !== '' ? $errorOutput : $output);

        return $selectedOutput === '' ? $fallback : $selectedOutput;
    }
}
