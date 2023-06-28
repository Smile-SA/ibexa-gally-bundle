<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

class RangeFilter implements Filter
{
    /**
     * Range filter compare the value of a field
     *
     * Use only $gte or $gt not both a te same time
     * Use only $lte or $lt not both a te same time
     * @param string $field the field to compare the value
     * @param string|null $gte the value is greater than or equal $gte
     * @param string|null $lte the value is lesser than or equal $lte
     * @param string|null $gt the value is greater than $gt
     * @param string|null $lt the value is less than $lt
     */
    public function __construct(
        private readonly string $field,
        private readonly ?string $gte = null,
        private readonly ?string $lte = null,
        private readonly ?string $gt = null,
        private readonly ?string $lt = null,
    ) {
    }

    public function toGraphQL(): string
    {
        $graphql = <<<GRAPHQL
                rangeFilter: {
                    field: "$this->field",
                GRAPHQL;
        if (!empty($this->gte)) {
            $graphql .= <<<GRAPHQL
                        gte: "$this->gte";
                    GRAPHQL;
        }
        if (!empty($this->gt)) {
            $graphql .= <<<GRAPHQL
                        gt: "$this->gt";
                    GRAPHQL;
        }
        if (!empty($this->lte)) {
            $graphql .= <<<GRAPHQL
                        lte: "$this->lte";
                    GRAPHQL;
        }
        if (!empty($this->lt)) {
            $graphql .= <<<GRAPHQL
                        lt: "$this->lt";
                    GRAPHQL;
        }
        $graphql .= <<<GRAPHQL
                }
                GRAPHQL;

        return $graphql;
    }
}
