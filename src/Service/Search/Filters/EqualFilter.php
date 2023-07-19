<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class EqualFilter implements Filter
{
    /**
     * EqualFilter search if the value of a field is $value
     * or if $value an array search the value of a field inside the $value array
     * @param string $field field to check the value
     * @param string|array $value the value string or array
     */
    public function __construct(
        private readonly string $field,
        private readonly string|array $value
    ) {
    }

    public function toGraphQL(): string
    {
        $graphql = <<<GRAPHQL
                equalFilter: {
                    field: "$this->field",
                GRAPHQL;
        if (is_string($this->value)) {
            $graphql .= <<<GRAPHQL
                        eq: "$this->value";
                    GRAPHQL;
        }
        if (is_array($this->value)) {
            $graphql .= <<<GRAPHQL
                        in: [
                    GRAPHQL;
            foreach ($this->value as $value) {
                $graphql .= <<<GRAPHQL
                        "$value",
                    GRAPHQL;
            }
            $graphql .= <<<GRAPHQL
                        ]
                    GRAPHQL;
        }
        $graphql .= <<<GRAPHQL
                }
                GRAPHQL;

        return $graphql;
    }
}
