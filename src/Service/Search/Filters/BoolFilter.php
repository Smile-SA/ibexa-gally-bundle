<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class BoolFilter implements Filter
{
    /**
     * Bool filter with must, should or not
     * It compare to another filter, example :
     * should([matchFilter, anotherMatchFilter])
     *
     * @param Filter[]|null $must
     * @param Filter[]|null $should
     * @param Filter[]|null $not
     */
    public function __construct(
        private readonly ?array $must = null,
        private readonly ?array $should = null,
        private readonly ?array $not = null,
    ) {
    }

    public function toGraphQL(): string
    {
        $graphql = <<<GRAPHQL
                boolFilter: {
                GRAPHQL;
        if (null !== $this->must) {
            $graphql .= "_must: [";
            foreach ($this->must as $filter) {
                $graphql .= "{".$filter->toGraphQL()."}";
            }
            $graphql .= "]";
        }
        if (null !== $this->should) {
            $graphql .= "_should: [";
            foreach ($this->should as $filter) {
                $graphql .= "{".$filter->toGraphQL()."}";
            }
            $graphql .= "]";
        }
        if (null !== $this->not) {
            $graphql .= "_not: [";
            foreach ($this->not as $filter) {
                $graphql .= "{".$filter->toGraphQL()."}";
            }
            $graphql .= "]";
        }
        $graphql .= <<<GRAPHQL
                }
                GRAPHQL;

        return $graphql;
    }
}
