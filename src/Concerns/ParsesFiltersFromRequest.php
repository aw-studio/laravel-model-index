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
                $filters[] = $this->parseCondition($key, $value);
            }
        }

        return $filters;
    }

    /**
     * Parse a filter condition.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return array
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
            return [$field, 'in', $value];
        }

        // if it's an associative array like ['operator' => 'val']
        if (is_array($value)) {
            foreach ($value as $operator => $val) {
                return [$field, $this->transformOperator($operator), $this->transformValue($operator, $val ?? '')];
            }
        }

        // simple scalar value
        return [$field, '=', $value];
    }

    /**
     * Transform a filter operator to a query operator.
     *
     * @return string
     */
    protected function transformOperator(string $operator)
    {
        return match ($operator) {
            '$eq' => '=',
            '$ne' => '!=',
            '$lt' => '<',
            '$lte' => '<=',
            '$gt' => '>',
            '$gte' => '>=',
            '$in' => 'in',
            '$notIn' => 'not in',
            '$contains' => 'like',
            '$notContains' => 'not like',
            '$between' => 'between',
            '$startsWith' => 'like',
            '$endsWith' => 'like',
            '$null' => 'null',
            '$notNull' => 'not null',
            default => '=',
        };
    }

    /**
     * Transform a filter value based on the operator.
     *
     * @return string|array
     */
    protected function transformValue(string $operator, string|array $value)
    {
        return match ($operator) {
            '$contains' => "%{$value}%",
            '$notContains' => "%{$value}%",
            '$startsWith' => "{$value}%",
            '$endsWith' => "%{$value}",
            // '$between' => array_map('intval', (array) $value), // TODO: do we have to cast other types?
            default => $value,
        };
    }
}
