<?php

namespace Smile\Ibexa\Gally\Service\Search\Sort;

class SortOrder
{
    public function __construct(
        private readonly string $field,
        private readonly SortDirection $sortDirection
    )
    {
    }

    public function getVariable(): array
    {
        return [
            "field" => $this->field,
            "direction" => $this->sortDirection
        ];
    }
}