<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class JobFilterService
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function applyFilters(string $filterString): Builder
    {
        $conditions = $this->parseFilterString($filterString);
        $this->buildQuery($conditions);
        dump($this->query->toSql()); // Shows the raw SQL with placeholders
        dump($this->query->getBindings()); // Shows the actual values for the placeholders
        exit;
        return $this->query;
    }

    protected function parseFilterString(string $filterString): array
    {
        // Remove unnecessary whitespace
        $filterString = trim($filterString);

        // Initialize an empty array to store conditions
        $conditions = [];

        // Use a stack to handle nested conditions
        $stack = [];
        $currentCondition = '';

        // Debugging: Log the input filter string
        dump("Parsing filter string: $filterString");

        // Iterate through each character in the filter string
        for ($i = 0; $i < strlen($filterString); $i++) {
            $char = $filterString[$i];

            // Debugging: Log the current character and stack state
            // dump("Processing character: $char, Stack: " . json_encode($stack));

            if ($char === '(') {
                // Push the current condition onto the stack and start a new condition
                if (!empty($currentCondition)) {
                    $stack[] = $currentCondition;
                    $currentCondition = '';
                }
            } elseif ($char === ')') {
                // Parse the current condition and add it to the conditions array
                if (!empty($currentCondition)) {
                    $parsedCondition = $this->parseCondition($currentCondition);
                    if (!empty($stack)) {
                        // Pop the previous condition from the stack and combine it
                        $previousCondition = array_pop($stack);
                        $conditions[] = [$previousCondition, 'AND', $parsedCondition]; // Default to AND
                    } else {
                        $conditions[] = $parsedCondition;
                    }
                    $currentCondition = '';
                }
            } elseif ($char === ' ' && strtoupper(substr($filterString, $i, 4)) === ' AND') {
                // Handle logical operator 'AND'
                if (!empty($currentCondition)) {
                    $parsedCondition = $this->parseCondition($currentCondition);
                    $conditions[] = $parsedCondition;
                    $conditions[] = 'AND';
                    $currentCondition = '';
                    $i += 3; // Skip the 'AND' characters
                }
            } elseif ($char === ' ' && strtoupper(substr($filterString, $i, 3)) === ' OR') {
                // Handle logical operator 'OR'
                if (!empty($currentCondition)) {
                    $parsedCondition = $this->parseCondition($currentCondition);
                    $conditions[] = $parsedCondition;
                    $conditions[] = 'OR';
                    $currentCondition = '';
                    $i += 2; // Skip the 'OR' characters
                }
            } else {
                // Append the character to the current condition
                $currentCondition .= $char;
            }
        }

        // Parse any remaining condition outside parentheses
        if (!empty($currentCondition)) {
            $parsedCondition = $this->parseCondition($currentCondition);
            $conditions[] = $parsedCondition;
        }

        // Debugging: Log the final conditions array
        dump(" Parsed conditions: " . json_encode($conditions));

        return $conditions;
    }

    protected function parseCondition(string $condition): array
    {
        // Debugging: Log the condition being parsed
        dump("Parsing condition: $condition");

        // Regex to match the condition pattern: field operator value
        $pattern = '/^([a-zA-Z0-9_:]+)\s*(>=|<=|!=|>|<|=|HAS_ANY|IS_ANY|LIKE|IN|EXISTS)\s*(.*)$/';
        if (preg_match($pattern, trim($condition), $matches)) {
            $field = $matches[1];
            $operator = $matches[2];
            $value = trim($matches[3], "'\""); // Remove extra quotes if any

            // Convert boolean-like values
            if (strtolower($value) === 'true') {
                $value = true;
            } elseif (strtolower($value) === 'false') {
                $value = false;
            } elseif (is_numeric($value)) {
                $value = +$value; // Convert numeric strings to numbers
            }

            // Handle special cases for operators like HAS_ANY, IS_ANY, IN
            if ($operator === 'HAS_ANY' || $operator === 'IS_ANY' || $operator === 'IN') {
                $value = array_map('trim', explode(',', trim($value, '()')));
            }

            return [$field, $operator, $value];
        }

        // Debugging: Log if the condition could not be parsed
        dump("Could not parse condition: $condition");

        return [];
    }


    protected function buildQuery(array $conditions)
    {
        $this->query->where(function ($query) use ($conditions) {
            $logicalOperator = 'AND'; // Default operator

            foreach ($conditions as $condition) {
                if (is_array($condition) && count($condition) === 3) {
                    // It's a valid condition
                    [$field, $operator, $value] = $condition;

                    if ($logicalOperator === 'AND') {
                        $query->where($field, $operator, $value);
                    } else {
                        $query->orWhere($field, $operator, $value);
                    }
                } elseif ($condition === 'AND' || $condition === 'OR') {
                    // Set the logical operator for the next condition
                    $logicalOperator = $condition;
                }
            }
        });
    }


    protected function applyCondition(array $condition)
    {
        $field = $condition[0];
        $operator = $condition[1];
        $value = $condition[2];

        if (Str::startsWith($field, 'attribute:')) {
            // Handle EAV attributes
            $attributeName = Str::after($field, 'attribute:');
            $this->query->whereHas('attributes', function ($query) use ($attributeName, $operator, $value) {
                $query->where('name', $attributeName)
                    ->where('value', $operator, $value);
            });
        } elseif (in_array($field, ['languages', 'locations', 'categories'])) {
            // Handle relationships
            $this->applyRelationshipCondition($field, $operator, $value);
        } else {
            // Handle basic fields
            $this->query->where($field, $operator, $value);
        }
    }

    protected function applyRelationshipCondition(string $relationship, string $operator, $value)
    {
        if ($operator === 'HAS_ANY') {
            $this->query->whereHas($relationship, function ($query) use ($value) {
                $query->whereIn('name', $value);
            });
        } elseif ($operator === 'IS_ANY') {
            $this->query->whereHas($relationship, function ($query) use ($value) {
                $query->whereIn('city', $value); // Adjust for location-specific fields
            });
        } elseif ($operator === 'EXISTS') {
            $this->query->whereHas($relationship);
        }
    }
}
