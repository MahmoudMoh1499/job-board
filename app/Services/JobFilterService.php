<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;

class JobFilterService
{
    /**
     * Parse the filter string and build the query.
     *
     * @param string|null $filterString
     * @return Builder
     */
    public function filter(?string $filterString): Builder
    {
        $query = Job::query();

        if (!$filterString) {
            return $query->where('status', 'published');
        }

        // Add joins for EAV filtering
        $query->leftJoin('job_attribute_values', 'jobs.id', '=', 'job_attribute_values.job_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'job_attribute_values.attribute_id')
            ->select('jobs.*')
            ->distinct();

        $this->parseFilter($query, $filterString);

        return $query;
    }

    /**
     * Parse the filter string recursively.
     *
     * @param Builder $query
     * @param string $filterString
     * @param string $logicalOperator
     * @return void
     */
    protected function parseFilter(Builder $query, string $filterString, string $logicalOperator = 'AND'): void
    {
        if ($filterString[0] === '(' && $this->hasBalancedParentheses($filterString)) {
            $this->handleGroupedFilters($query, $filterString, $logicalOperator);
            return;
        }

        if (strpos($filterString, ' AND ') !== false) {
            $parts = explode(' AND ', $filterString);
            $this->applyLogicalParts($query, $parts, 'AND');
            return;
        }

        if (strpos($filterString, ' OR ') !== false) {
            $parts = explode(' OR ', $filterString);
            $this->applyLogicalParts($query, $parts, 'OR');
            return;
        }

        $this->applySingleCondition($query, $filterString, $logicalOperator);
    }

    /**
     * Apply multiple filter parts with the given logical operator.
     *
     * @param Builder $query
     * @param array $parts
     * @param string $operator
     * @return void
     */
    protected function applyLogicalParts(Builder $query, array $parts, string $operator): void
    {
        $method = $operator === 'AND' ? 'where' : 'orWhere';

        $query->$method(function ($subQuery) use ($parts, $operator) {
            foreach ($parts as $part) {
                $this->parseFilter($subQuery, trim($part), $operator);
            }
        });
    }

    /**
     * Apply a single filter condition.
     *
     * @param Builder $query
     * @param string $condition
     * @param string $logicalOperator
     * @return void
     */
    protected function applySingleCondition(Builder $query, string $condition, string $logicalOperator): void
    {
        $method = $logicalOperator === 'AND' ? 'where' : 'orWhere';

        if (strpos($condition, 'attribute:') === 0) {
            $this->applyAttributeFilter($query, $condition, $method);
            return;
        }

        if (
            strpos($condition, 'languages') === 0 ||
            strpos($condition, 'locations') === 0 ||
            strpos($condition, 'categories') === 0
        ) {
            $this->applyRelationshipFilter($query, $condition, $method);
            return;
        }

        $this->applyBasicFilter($query, $condition, $method);
    }

    /**
     * Apply filters for basic job fields.
     *
     * @param Builder $query
     * @param string $condition
     * @param string $method
     * @return void
     */
    protected function applyBasicFilter(Builder $query, string $condition, string $method): void
    {
        // Parse the condition to get field, operator, and value
        preg_match('/^([a-zA-Z_]+)\s*([=!<>]+|LIKE|IN|NOT IN)\s*(.+)$/', trim($condition), $matches);

        if (count($matches) < 4) {
            return; // Invalid format
        }

        $field = $matches[1];
        $operator = $this->mapOperator($matches[2]);
        $value = $this->parseValue($matches[3]);
        if ($operator === 'IN' || $operator === 'NOT IN') {
            $values = $this->parseArrayValue($value);
            $query->$method(function ($q) use ($field, $operator, $values) {
                $q->whereIn($field, $values, 'and', $operator === 'NOT IN');
            });

            return;
        }

        if ($operator === 'LIKE') {
            $query->$method($field, $operator, '%' . $value . '%');
            return;
        }

        $query->$method($field, $operator, $value);
    }

    /**
     * Apply filters for EAV attributes.
     *
     * @param Builder $query
     * @param string $condition
     * @param string $method
     * @return void
     */
    protected function applyAttributeFilter(Builder $query, string $condition, string $method): void
    {
        $condition = substr($condition, 10); // Remove the 'attribute:' prefix


        // Parse the condition to get attribute name, operator, and value
        preg_match('/^([a-zA-Z_]+)([=!<>]+|LIKE|IN|NOT IN)(.+)$/', $condition, $matches);

        if (count($matches) < 4) {
            return; // Invalid format
        }

        $attributeName = $matches[1];
        $operator = $this->mapOperator($matches[2]);
        $value = $this->parseValue($matches[3]);

        $attribute = Attribute::where('name', $attributeName)->first();

        if (!$attribute) {
            return; // Attribute not found
        }

        $query->$method(function ($subQuery) use ($attribute, $operator, $value, $attributeName) {
            $subQuery->whereHas('attributeValues', function ($q) use ($attribute, $operator, $value) {
                $q->where('attribute_id', $attribute->id);

                switch ($attribute->type) {
                    case 'number':
                        $q->whereRaw("CAST(value AS DECIMAL(10,2)) {$operator} ?", [(float)$value]);
                        break;

                    case 'date':
                        $q->whereRaw("CAST(value AS DATE) {$operator} ?", [$value]);
                        break;

                    case 'boolean':
                        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                        $q->where('value', $boolValue);
                        break;

                    case 'select':
                        if ($operator === 'IN' || $operator === 'NOT IN') {
                            $values = $this->parseArrayValue($value);
                            $q->whereIn('value', $values, 'and', $operator === 'NOT IN');
                        } else {
                            $q->where('value', $operator, $value);
                        }
                        break;

                    default:
                        if ($operator === 'LIKE') {
                            $q->where('value', 'LIKE', "%{$value}%");
                        } else {
                            $q->where('value', $operator, $value);
                        }
                }
            });
        });
    }

    /**
     * Apply filters for relationships (languages, locations, categories).
     *
     * @param Builder $query
     * @param string $condition
     * @param string $method
     * @return void
     */
    protected function applyRelationshipFilter(Builder $query, string $condition, string $method): void
    {
        preg_match('/^([a-zA-Z_]+)\s+(=|HAS_ANY|IS_ANY|EXISTS)\s*(.*)$/', $condition, $matches);

        if (count($matches) < 3) {
            return; // Invalid format
        }

        $relation = $matches[1];
        $operator = $matches[2];
        $value = isset($matches[3]) ? $this->parseValue($matches[3]) : null;

        switch ($operator) {
            case '=':
                $values = $this->parseArrayValue($value);
                $query->$method(function ($q) use ($relation, $values) {
                    // Get count of specified values
                    $valueCount = count($values);

                    // Get relation model and field
                    $relationalField = $this->getRelationalField($relation);

                    // Jobs must have exactly these values (no more, no less)
                    $q->has($relation, '=', $valueCount)
                        ->whereHas($relation, function ($subQuery) use ($values, $relationalField) {
                            $subQuery->whereIn($relationalField, $values);
                        }, '=', $valueCount);
                });
                break;

            case 'HAS_ANY':
                $values = $this->parseArrayValue($value);
                $query->$method(function ($q) use ($relation, $values) {
                    $relationalField = $this->getRelationalField($relation);

                    $q->whereHas($relation, function ($subQuery) use ($values, $relationalField) {
                        $subQuery->whereIn($relationalField, $values);
                    });
                });
                break;

            case 'IS_ANY':
                $values = $this->parseArrayValue($value);
                $query->$method(function ($q) use ($relation, $values) {
                    // Special case for Remote location
                    if ($relation === 'locations' && in_array('Remote', $values)) {
                        $q->where(function ($subQuery) use ($relation, $values) {
                            $relationalField = $this->getRelationalField($relation);
                            $filteredValues = array_filter($values, function ($v) {
                                return $v !== 'Remote';
                            });

                            if (!empty($filteredValues)) {
                                $subQuery->whereHas($relation, function ($locationQuery) use ($filteredValues, $relationalField) {
                                    $locationQuery->whereIn($relationalField, $filteredValues);
                                });
                                $subQuery->orWhere('is_remote', true);
                            } else {
                                $subQuery->where('is_remote', true);
                            }
                        });
                    } else {
                        $relationalField = $this->getRelationalField($relation);
                        $q->whereHas($relation, function ($subQuery) use ($values, $relationalField) {
                            $subQuery->whereIn($relationalField, $values);
                        });
                    }
                });
                break;

            case 'EXISTS':
                $query->$method(function ($q) use ($relation) {
                    $q->has($relation);
                });
                break;
        }
    }

    /**
     * Get the field name to use for filtering on a relationship.
     *
     * @param string $relation
     * @return string
     */
    protected function getRelationalField(string $relation): string
    {
        switch ($relation) {
            case 'languages':
                return 'languages.name';
            case 'locations':
                return 'locations.city'; // TODO: be more complex for locations
            case 'categories':
                return 'categories.name';
            default:
                return $relation . '.name';
        }
    }

    /**
     * Handle grouped filters in parentheses.
     *
     * @param Builder $query
     * @param string $filterString
     * @param string $logicalOperator
     * @return void
     */
    protected function handleGroupedFilters(Builder $query, string $filterString, string $logicalOperator): void
    {
        // Check if this is a valid grouped filter
        if (empty($filterString) || strlen($filterString) < 2) {
            return;
        }

        // Extract inner content of the outermost parentheses
        if ($filterString[0] === '(' && $filterString[strlen($filterString) - 1] === ')') {
            // Check if the entire string is wrapped in parentheses
            $inner = substr($filterString, 1, -1);
            if ($this->isCompleteExpression($inner)) {
                $this->parseFilter($query, $inner, $logicalOperator);
                return;
            }
        }

        // Look for logical operators between groups
        $pattern = '/(\([^()]*(?:(?R)[^()]*)*\))\s+(AND|OR)\s+(.+)/i';
        if (preg_match($pattern, $filterString, $matches)) {
            $leftGroup = $matches[1];
            $operator = $matches[2];
            $rightSide = $matches[3];

            $method = $logicalOperator === 'AND' ? 'where' : 'orWhere';
            $query->$method(function ($subQuery) use ($leftGroup, $operator, $rightSide) {
                // Remove outer parentheses from left group
                $leftContent = substr($leftGroup, 1, -1);
                $this->parseFilter($subQuery, $leftContent, 'AND');

                $nestedMethod = $operator === 'AND' ? 'where' : 'orWhere';
                $subQuery->$nestedMethod(function ($nestedQuery) use ($rightSide) {
                    $this->parseFilter($nestedQuery, $rightSide, 'AND');
                });
            });
        } else {
            // If no logical operators between groups, just apply the whole thing
            $method = $logicalOperator === 'AND' ? 'where' : 'orWhere';
            $query->$method(function ($subQuery) use ($filterString) {
                // Remove outer parentheses
                $content = substr($filterString, 1, -1);
                $this->parseFilter($subQuery, $content, 'AND');
            });
        }
    }

    /**
     * Check if parentheses are balanced in the filter string.
     *
     * @param string $filterString
     * @return bool
     */
    protected function hasBalancedParentheses(string $filterString): bool
    {
        $count = 0;
        for ($i = 0; $i < strlen($filterString); $i++) {
            if ($filterString[$i] === '(') {
                $count++;
            } else if ($filterString[$i] === ')') {
                $count--;
            }

            if ($count < 0) {
                return false;
            }
        }

        return $count === 0;
    }

    /**
     * Check if a string represents a complete expression (balanced parentheses).
     *
     * @param string $expression
     * @return bool
     */
    protected function isCompleteExpression(string $expression): bool
    {
        return $this->hasBalancedParentheses($expression);
    }

    /**
     * Map string operators to SQL operators.
     *
     * @param string $operator
     * @return string
     */
    protected function mapOperator(string $operator): string
    {
        $map = [
            '=' => '=',
            '!=' => '!=',
            '<>' => '!=',
            '>' => '>',
            '<' => '<',
            '>=' => '>=',
            '<=' => '<=',
            'LIKE' => 'LIKE',
            'IN' => 'IN',
            'NOT IN' => 'NOT IN'
        ];

        return $map[$operator] ?? '=';
    }

    /**
     * Parse a value from the filter string.
     *
     * @param string $value
     * @return mixed
     */
    protected function parseValue(string $value)
    {
        $value = trim($value);

        if (strpos($value, '(') === 0 && substr($value, -1) === ')') {
            return $this->parseArrayValue($value);
        }

        if (strpos($value, '"') === 0 && substr($value, -1) === '"') {
            return substr($value, 1, -1);
        }

        if (strpos($value, "'") === 0 && substr($value, -1) === "'") {
            return substr($value, 1, -1);
        }

        if (strtolower($value) === 'true') {
            return true;
        }

        if (strtolower($value) === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * Parse an array value from the filter string.
     *
     * @param string $value
     * @return array
     */
    protected function parseArrayValue(string|array $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (strpos($value, '(') === 0 && substr($value, -1) === ')') {
            $value = substr($value, 1, -1);
        }

        $parts = explode(',', $value);

        return array_map(function ($part) {
            return $this->parseValue(trim($part));
        }, $parts);
    }
}
