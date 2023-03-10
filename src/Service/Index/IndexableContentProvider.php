<?php

namespace Smile\Ibexa\Gally\Service\Index;

class IndexableContentProvider
{
    public function __construct(private readonly array $parameters)
    {
    }

    public function getContentTypes(): array
    {
        return $this->parameters['content_types'];
    }

    public function getFieldTypes(): array
    {
        return $this->parameters['field_types'];
    }
}
