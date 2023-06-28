<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class ExistFilter implements Filter
{
    /**
     * ExistFilter check if a field exist
     * @param string $field
     */
    public function __construct(
        private readonly string $field
    ) {
    }

    public function toGraphQL(): string
    {
        return <<<GRAPHQL
                existFilter: {
                    field: "$this->field",
                }
                GRAPHQL;
    }
}
