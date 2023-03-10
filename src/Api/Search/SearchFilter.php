<?php

namespace Smile\Ibexa\Gally\Api\Search;

class SearchFilter
{
    private array $boolFilter;
    private array $equalFilter;
    private array $matchFilter;
    private array $rangeFilter;
    private array $existFilter;

    public function __construct()
    {
        $this->boolFilter = [];
        $this->equalFilter = [];
        $this->matchFilter = [];
        $this->rangeFilter = [];
        $this->existFilter = [];
    }

    /**
     * Bool filter with must should or not
     * Use another searchfilter in
     * example : not (equalfilter)
     *
     * @param SearchFilter|null $must
     * @param SearchFilter|null $should
     * @param SearchFilter|null $not
     *
     * @return void
     */
    public function setBoolFilter(SearchFilter $must = null, SearchFilter $should = null, SearchFilter $not = null): void
    {
        $this->boolFilter['must'] = $must;
        $this->boolFilter['should'] = $should;
        $this->boolFilter['not'] = $not;
    }

    /**
     * Equal filter search if the exact value of a field with a value or an array
     * Only 'equal' or only 'in' should be filled
     *
     * @param string $field the field
     * @param string|null $equal
     * @param array|null $in
     *
     * @return void
     */
    public function setEqualFilter(string $field, string $equal = null, array $in = null): void
    {
        $this->equalFilter['field'] = $field;
        $this->equalFilter['eq'] = $equal;
        $this->equalFilter['in'] = $in;
    }

    /**
     * Match filter search a string in the value of a field.
     *
     * @param string $field the field
     * @param string $match the search value
     *
     * @return void
     */
    public function setMatchFilter(string $field, string $match): void
    {
        $this->matchFilter['field'] = $field;
        $this->matchFilter['match'] = $match;
    }

    /**
     * Range filter
     * Do not use 'gt' and 'gte' in the same filter
     * Do not use 'lt' and 'lte' in the same filter
     *
     * @param string $field the field
     * @param string|null $gte greater than or equal
     * @param string|null $lte lesser than or equal
     * @param string|null $gt greater than
     * @param string|null $lt less than
     *
     * @return void
     */
    public function setRangeFilter(
        string $field,
        string $gte = null,
        string $lte = null,
        string $gt = null,
        string $lt = null
    ): void {
        $this->matchFilter['field'] = $field;
        $this->matchFilter['gte'] = $gte;
        $this->matchFilter['lte'] = $lte;
        $this->matchFilter['lt'] = $lt;
        $this->matchFilter['gt'] = $gt;
    }

    /**
     * Check if a field exist.
     *
     * @param string $field
     *
     * @return void
     */
    public function setExistFilter(string $field): void
    {
        $this->existFilter['field'] = $field;
    }

    /**
     * Give the graphql query with all the filter set.
     * @return string
     */
    public function toGraphQL(string $type = "filter"): string
    {
        $graphql = <<<GRAPHQL
            $type: {

            GRAPHQL;

        // Add bool filter
        if (!empty($this->boolFilter)) {
            $must = ($this->boolFilter['must'])->toGraphQl();
            $should = ($this->boolFilter['should'])->toGraphQl();
            $not = ($this->boolFilter['not'])->toGraphQl();
            $graphql .= <<<GRAPHQL
                boolFilter: {
                GRAPHQL;
            if (!empty($must)) {
                $graphql .= $must;
            }
            if (!empty($should)) {
                $graphql .= $should;
            }
            if (!empty($not)) {
                $graphql .= $not;
            }

            $graphql .= <<<GRAPHQL
                }
                GRAPHQL;
        }

        // Add equal filter
        if (!empty($this->equalFilter)) {
            $field = $this->equalFilter['field'];
            $eq = $this->equalFilter['eq'];
            $in = $this->equalFilter['in'];
            $graphql .= <<<GRAPHQL
                equalFilter: {
                    field: "$field",
                GRAPHQL;
            if (!empty($eq)) {
                $graphql .= <<<GRAPHQL
                        eq: "$eq";
                    GRAPHQL;
            }
            if (!empty($in)) {
                $graphql .= <<<GRAPHQL
                        in: [
                    GRAPHQL;
                foreach ($in as $value) {
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
        }

        // Add match filter
        if (!empty($this->matchFilter)) {
            $field = $this->matchFilter['field'];
            $match = $this->matchFilter['match'];
            $graphql .= <<<GRAPHQL
                matchFilter: {
                    field: "$field",
                    match: "$match"
                }
                GRAPHQL;
        }

        // Add range filter
        if (!empty($this->rangeFilter)) {
            $field = $this->matchFilter['field'];
            $gte = $this->matchFilter['gte'];
            $gt = $this->matchFilter['gt'];
            $lte = $this->matchFilter['lte'];
            $lt = $this->matchFilter['lt'];
            $graphql .= <<<GRAPHQL
                rangeFilter: {
                    field: "$field",
                GRAPHQL;
            if (!empty($gte)) {
                $graphql .= <<<GRAPHQL
                        gte: "$gte";
                    GRAPHQL;
            }
            if (!empty($gt)) {
                $graphql .= <<<GRAPHQL
                        gt: "$gt";
                    GRAPHQL;
            }
            if (!empty($lte)) {
                $graphql .= <<<GRAPHQL
                        lte: "$lte";
                    GRAPHQL;
            }
            if (!empty($lt)) {
                $graphql .= <<<GRAPHQL
                        lt: "$lt";
                    GRAPHQL;
            }

            $graphql .= <<<GRAPHQL
                }
                GRAPHQL;
        }

        // add exist filter
        if (!empty($this->existFilter)) {
            $field = $this->existFilter['field'];
            $graphql .= <<<GRAPHQL
                existFilter: {
                    field: "$field",
                }
                GRAPHQL;
        }

        $graphql .= <<<GRAPHQL
            }
            GRAPHQL;

        return $graphql;
    }
}
