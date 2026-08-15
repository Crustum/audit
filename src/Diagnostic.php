<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Cake\Utility\Inflector;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Result\Status;
use InvalidArgumentException;

/**
 * Base class for application diagnostics.
 */
abstract class Diagnostic
{
    /**
     * Human-readable diagnostic name.
     *
     * @var string
     */
    public string $name;

    /**
     * Diagnostic group.
     *
     * @var string
     */
    public string $group;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $className = static::class;
        $basename = substr($className, (int)strrpos($className, '\\') + 1);
        $underscored = Inflector::underscore($basename);

        $this->name ??= ucfirst(str_replace('_', ' ', $underscored));
        $this->group ??= str_replace('_', '-', $underscored);
    }

    /**
     * Run the diagnostic.
     *
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    abstract public function check(): DiagnosticResult;

    /**
     * Get named message definitions.
     *
     * @return array<string, string|\Crustum\Audit\Result\Message>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Create a passing result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function pass(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Pass, $message, $replace);
    }

    /**
     * Create a failing result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function fail(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Fail, $message, $replace);
    }

    /**
     * Create a warning result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function warn(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Warn, $message, $replace);
    }

    /**
     * Create a notice result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function notice(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Notice, $message, $replace);
    }

    /**
     * Create a skipped result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function skip(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Skip, $message, $replace);
    }

    /**
     * Create an error result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    protected function error(string $message, array $replace = []): DiagnosticResult
    {
        return $this->diagnosticResult(Status::Error, $message, $replace);
    }

    /**
     * Create a passing fix result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\FixResult
     */
    protected function fixed(string $message, array $replace = []): FixResult
    {
        return $this->diagnosticFixResult(Status::Pass, $message, $replace);
    }

    /**
     * Create a failed fix result from a named message.
     *
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\FixResult
     */
    protected function fixFailed(string $message, array $replace = []): FixResult
    {
        return $this->diagnosticFixResult(Status::Fail, $message, $replace);
    }

    /**
     * Get a named message definition.
     *
     * @param string $message Message name.
     * @return \Crustum\Audit\Result\Message
     */
    protected function message(string $message): Message
    {
        $definition = $this->messages()[$message] ?? null;

        if (is_string($definition)) {
            return Message::make($definition);
        }

        if (!$definition instanceof Message) {
            throw new InvalidArgumentException(sprintf(
                'Message [%s] is not defined for diagnostic [%s].',
                $message,
                static::class,
            ));
        }

        return $definition;
    }

    /**
     * Get a machine-readable result code.
     *
     * @param string $message Message name.
     * @return string
     */
    protected function code(string $message): string
    {
        return $this->codePrefix() . '.' . $message;
    }

    /**
     * Get a machine-readable fix result code.
     *
     * @param string $message Message name.
     * @return string
     */
    protected function fixCode(string $message): string
    {
        return $this->codePrefix() . '.fix.' . $message;
    }

    /**
     * Normalize replacement values into tokens.
     *
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return array<string, string>
     */
    protected function tokens(array $replace): array
    {
        $tokens = [];

        foreach ($replace as $key => $value) {
            $tokens['{' . $key . '}'] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'true' : 'false',
                default => (string)$value,
            };
        }

        return $tokens;
    }

    /**
     * Get the Composer package providing the diagnostic.
     *
     * @return string|null
     */
    public function package(): ?string
    {
        return DiagnosticSource::resolve(static::class)->package;
    }

    /**
     * Create a diagnostic result from a message definition.
     *
     * @param \Crustum\Audit\Result\Status $status Result status.
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\DiagnosticResult
     */
    private function diagnosticResult(Status $status, string $message, array $replace): DiagnosticResult
    {
        $definition = $this->message($message);
        $tokens = $this->tokens($replace);
        $result = new DiagnosticResult(
            $status,
            strtr($definition->summary, $tokens),
            $this->code($message),
        );

        if ($definition->remediation !== null) {
            $result->suggest(strtr($definition->remediation, $tokens));
        }

        if ($definition->confirmation !== null) {
            $result->confirmUsing(strtr($definition->confirmation, $tokens));
        }

        foreach ($definition->links as $link) {
            $result->link($link->replace($tokens));
        }

        return $result;
    }

    /**
     * Create a fix result from a message definition.
     *
     * @param \Crustum\Audit\Result\Status $status Result status.
     * @param string $message Message name.
     * @param array<string, bool|float|int|string|\Stringable|null> $replace Replacement values.
     * @return \Crustum\Audit\Result\FixResult
     */
    private function diagnosticFixResult(Status $status, string $message, array $replace): FixResult
    {
        $definition = $this->message($message);

        return new FixResult(
            $status,
            strtr($definition->summary, $this->tokens($replace)),
            $this->fixCode($message),
        );
    }

    /**
     * Get the kebab-case code prefix.
     *
     * @return string
     */
    private function codePrefix(): string
    {
        $className = static::class;
        $basename = substr($className, (int)strrpos($className, '\\') + 1);

        return str_replace('_', '-', Inflector::underscore($basename));
    }
}
