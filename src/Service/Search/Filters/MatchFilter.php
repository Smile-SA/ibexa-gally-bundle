<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class MatchFilter implements Filter
{
    /**
     * Create a match filter that search a string in a field
     *
     * @param string $field field to search in
     * @param string $match value to search
     */
    public function __construct(
        private readonly string $field,
        private readonly string $match,
    ) {
    }

    public function toGraphQL(): string
    {
        return <<<GRAPHQL
            matchFilter: {
                field: "$this->field",
                match: "$this->match"
            }
            GRAPHQL;
    }
}
