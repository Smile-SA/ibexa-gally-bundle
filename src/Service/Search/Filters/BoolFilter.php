<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class BoolFilter implements Filter
{
    /**
     * Bool filter with must, should or not
     * It compare to another filter, example :
     * not(EqualFilter)
     *
     * @param Filter|null $must
     * @param Filter|null $should
     * @param Filter|null $not
     */
    public function __construct(
        private readonly ?Filter $must = null,
        private readonly ?Filter $should = null,
        private readonly ?Filter $not = null,
    ) {
    }

    public function toGraphQL(): string
    {
        $graphql = <<<GRAPHQL
                boolFilter: {
                GRAPHQL;
        if (null !== $this->must) {
            $graphql .= $this->must->toGraphQL();
        }
        if (null !== $this->should) {
            $graphql .= $this->should->toGraphQL();
        }
        if (null !== $this->not) {
            $graphql .= $this->not->toGraphQL();
        }
        $graphql .= <<<GRAPHQL
                }
                GRAPHQL;

        return $graphql;
    }
}
