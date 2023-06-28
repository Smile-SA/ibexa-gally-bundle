<?php

namespace Smile\Ibexa\Gally\Service\Search\Filters;

interface Filter
{
    public function toGraphQL(): string;
}
