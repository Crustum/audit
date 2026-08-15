<?php
declare(strict_types=1);

namespace Crustum\Audit;

/**
 * Immutable diagnostic selector constraints.
 */
class DiagnosticSelection
{
    /**
     * Normalized selectors diagnostics must match.
     *
     * @var list<string>
     */
    protected readonly array $only;

    /**
     * Normalized selectors diagnostics must not match.
     *
     * @var list<string>
     */
    protected readonly array $except;

    /**
     * Only constraints that must all match.
     *
     * @var list<list<string>>
     */
    protected readonly array $onlyConstraints;

    /**
     * Constructor.
     *
     * @param iterable<array-key, string> $only Included selectors.
     * @param iterable<array-key, string> $except Excluded selectors.
     * @param list<iterable<array-key, string>> $onlyConstraints Prior included constraints.
     */
    public function __construct(
        iterable $only = [],
        iterable $except = [],
        array $onlyConstraints = [],
    ) {
        $this->only = self::normalize($only);
        $this->except = self::normalize($except);
        $constraints = [];

        foreach ($onlyConstraints as $constraint) {
            $normalized = self::normalize($constraint);

            if ($normalized !== []) {
                $constraints[] = $normalized;
            }
        }

        if ($constraints === [] && $this->only !== []) {
            $constraints[] = $this->only;
        }

        $this->onlyConstraints = $constraints;
    }

    /**
     * Create a normalized selection.
     *
     * @param iterable<array-key, string> $only Included selectors.
     * @param iterable<array-key, string> $except Excluded selectors.
     * @return self
     */
    public static function make(iterable $only = [], iterable $except = []): self
    {
        return new self(only: $only, except: $except);
    }

    /**
     * Add another selection constraint.
     *
     * @param iterable<array-key, string> $only Included selectors.
     * @param iterable<array-key, string> $except Excluded selectors.
     * @return self
     */
    public function constrain(iterable $only = [], iterable $except = []): self
    {
        $only = self::normalize($only);
        $except = self::normalize($except);

        return new self(
            only: $only !== [] ? $only : $this->only,
            except: [...$this->except, ...$except],
            onlyConstraints: [
                ...$this->onlyConstraints,
                ...($only !== [] ? [$only] : []),
            ],
        );
    }

    /**
     * Determine whether a diagnostic matches.
     *
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return bool
     */
    public function matches(Diagnostic $diagnostic): bool
    {
        foreach ($this->onlyConstraints as $criteria) {
            if (!$this->matchesAny($criteria, $diagnostic)) {
                return false;
            }
        }

        if ($this->except !== [] && $this->matchesAny($this->except, $diagnostic)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize selector values.
     *
     * @param iterable<array-key, string> $values Selector values.
     * @return list<string>
     */
    protected static function normalize(iterable $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            foreach (explode(',', $value) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $normalized[$part] = $part;
                }
            }
        }

        return array_values($normalized);
    }

    /**
     * Determine whether any criterion matches.
     *
     * @param list<string> $criteria Criteria.
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return bool
     */
    protected function matchesAny(array $criteria, Diagnostic $diagnostic): bool
    {
        return array_any($criteria, fn(string $criterion): bool => $this->matchesCriterion($criterion, $diagnostic));
    }

    /**
     * Determine whether a criterion matches.
     *
     * @param string $criterion Criterion.
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return bool
     */
    protected function matchesCriterion(string $criterion, Diagnostic $diagnostic): bool
    {
        $class = $diagnostic::class;
        $basename = substr($class, (int)strrpos($class, '\\') + 1);

        return in_array($criterion, [$class, $basename, $diagnostic->group], true)
            || $this->matchesPackage($criterion, $diagnostic);
    }

    /**
     * Determine whether a criterion matches the source package.
     *
     * @param string $criterion Criterion.
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return bool
     */
    protected function matchesPackage(string $criterion, Diagnostic $diagnostic): bool
    {
        $package = $diagnostic->package();

        if ($package === null) {
            return false;
        }

        return $criterion === $package
            || (str_ends_with($criterion, '/*') && str_starts_with($package, substr($criterion, 0, -1)));
    }
}
