<?php

namespace Smile\Ibexa\Gally\Service\Metadata;

class SourceFieldMappingProvider
{
    public function __construct(private readonly array $fields)
    {
    }

    public function getSourceFieldMapping(): array
    {
        $fieldTypeParameters = [];
        foreach ($this->fields as $field) {
            $fieldTypeParameters[$field['identifier']] = $field;
        }
        return $fieldTypeParameters;
    }
}
