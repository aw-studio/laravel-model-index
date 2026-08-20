<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait ParsesFiltersFromRequest
{
    /**
     * Get filters from the request.
     *
     * @return array
     */
    protected function getFiltersFromRequest(Request $request)
    {
        if (! $request->has('filter')) {
            return [];
        }

        return $this->parseFilters($request->get('filter'));
    }

    /**
     * Parse the filters from the request.
     *
     * @param  mixed  $filterNode
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseFilters($filterNode)
    {
        if (! is_array($filterNode)) {
            throw new \InvalidArgumentException('Invalid filter format.');
        }

        $filters = [];

        foreach ($filterNode as $key => $value) {

            if (in_array($key, ['$and', '$or'])) {
                $filters[$key] = array_map([$this, 'parseFilters'], $value);
            } else {
                // A field may carry several operators, e.g.
                // filter[age][$gte]=18&filter[age][$lte]=30. Each becomes its
                // own condition and takes the boolean of the group it sits in.
                foreach ($this->parseCondition($key, $value) as $condition) {
                    $filters[] = $condition;
                }
            }
        }

        return $filters;
    }

    /**
     * Parse a filter condition into one or more `[field, operator, value]`
     * triples.
     *
     * Returns a list because a single field may carry several operators —
     * `filter[age][$gte]=18&filter[age][$lte]=30` is two conditions. Earlier
     * versions returned after the first operator, silently discarding the rest.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     *
     * @throws \InvalidArgumentException
     */
    protected function parseCondition($field, $value)
    {
        // handle comma-separated string values
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        // if it's now a numeric-indexed array, treat as "in"
        if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
            return [[$field, 'in', $value]];
        }

        // if it's an associative array like ['operator' => 'val'], every entry
        // contributes a condition
        if (is_array($value)) {
            $conditions = [];

            foreach ($value as $operator => $val) {
                $conditions[] = [
                    $field,
                    $this->transformOperator($operator, $field),
                    $this->transformValue($operator, $val ?? '', $field),
                ];
            }

            return $conditions;
        }

        // simple scalar value
        return [[$field, '=', $value]];
    }

    /**
     * Transform a filter operator to a query operator.
     *
     * Unknown operators throw rather than falling back to equality. A silent
     * fallback turned a typo'd or unsupported operator into an empty result
     * set, which is indistinguishable from "there is no matching data".
     *
     * @param  string|null  $field
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function transformOperator(string $operator, $field = null)
    {
        return match ($operator) {
            '$eq' => '=',
            '$eqi' => 'ci =',
            '$ne' => '!=',
            '$nei' => 'ci !=',
            '$lt' => '<',
            '$lte' => '<=',
            '$gt' => '>',
            '$gte' => '>=',
            '$in' => 'in',
            '$notIn' => 'not in',
            '$contains' => 'like',
            '$notContains' => 'not like',
            '$containsi' => 'ci like',
            '$notContainsi' => 'ci not like',
            '$between' => 'between',
            '$startsWith' => 'like',
            '$endsWith' => 'like',
            '$null' => 'null',
            '$notNull' => 'not null',
            default => throw new \InvalidArgumentException("Unsupported operator '{$operator}' for field '{$field}'"),
        };
    }

    /**
     * Transform a filter value based on the operator.
     *
     * @return string|array
     */
    protected function transformValue(string $operator, string|array $value, $field = null)
    {
        return match ($operator) {
            '$contains' => "%{$value}%",
            '$notContains' => "%{$value}%",
            '$containsi' => "%{$value}%",
            '$notContainsi' => "%{$value}%",
            '$startsWith' => "{$value}%",
            '$endsWith' => "%{$value}",
            '$between' => $this->transformBetweenValue($value, $field),
            default => $value,
        };
    }

    /**
     * Normalize the bounds of a `$between` filter.
     *
     * Accepts both `filter[age][$between]=18,30` and the array form. The
     * bounds are deliberately not cast: casting to int works for numerics but
     * destroys date bounds such as `2026-01-01`, which are a first-class use
     * of this operator. Numeric strings are bound correctly by the driver as
     * they are, so no cast is the right answer for both cases.
     *
     * @param  string|array  $value
     * @param  string|null  $field
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function transformBetweenValue($value, $field = null)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $value = array_map(
            fn ($bound) => is_string($bound) ? trim($bound) : $bound,
            (array) $value
        );

        if (count($value) !== 2) {
            throw new \InvalidArgumentException(
                "The '\$between' operator for field '{$field}' expects exactly two values, got ".count($value).'.'
            );
        }

        return array_values($value);
    }
}
