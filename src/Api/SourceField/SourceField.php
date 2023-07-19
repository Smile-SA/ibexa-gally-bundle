<?php

namespace Smile\Ibexa\Gally\Api\SourceField;

use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use Smile\Ibexa\Gally\Api\Metadata\Metadata;
use Smile\Ibexa\Gally\Service\Client\Client;

class SourceField
{

    /**
     * @var \Gally\Rest\Model\SourceFieldSourceFieldApi[]
     */
    private array $sourceFieldsById;

    /**
     * @var \Gally\Rest\Model\SourceFieldSourceFieldApi[]
     */
    private array $sourceFieldsByCode;

    /**
     * @var \Gally\Rest\Model\SourceFieldLabel[]
     */
    private array $sourceFieldsLabelsByCode;

    /**
     * @var \Gally\Rest\Model\SourceFieldOption[]
     */
    private array $sourceFieldsOptionById;

    /**
     * @var \Gally\Rest\Model\SourceFieldOption[]
     */
    private array $sourceFieldsOptionByCode;

    /**
     * @var \Gally\Rest\Model\SourceFieldOptionLabel[]
     */
    private array $sourceFieldsOptionsLabelsById;

    public function __construct(
        private readonly Client $client,
        private readonly Metadata $metadata,
    ) {
        $this->getSourceFields(); // Init source field and their options data from the API.
    }

    /**
     * Create the mandatory id source field for gally.
     *
     * @throws \Exception
     */
    public function createIdSourceField(string $entityType): void
    {
        $attributeCode = 'id';

        $sourceFieldData = [
            'metadata' => '/metadata/' . $this->metadata->getMetadataIdByEntityType($entityType),
            'code' => $attributeCode,
            'defaultLabel' => 'id',
            'type' => 'int',
            'isSearchable' => false,
            'weight' => 1,
            'isSpellchecked' => false,
            'isFilterable' => false,
            'isSortable' => false,
            'isUsedForRules' => false,
        ];
        try {
            // If id is found, the field exist on Gally side. We will update the field.
            $sourceFieldData['id'] = $this->getSourceFieldIdByCode($attributeCode, $entityType);
        } catch (\Exception $exception) {
            // Do nothing, it means we will create the field instead of updating it.
        }

        $this->createSourceField($sourceFieldData);
    }

    /**
     * Create sourcefield with parameters
     * There are default parameters.
     *
     * @param string $entityType
     * @param string $code
     * @param string $defaultLabel
     * @param string $type
     * @param bool $isSearchable
     * @param int $weight
     * @param bool $isSpellchecked
     * @param bool $isFilterable
     * @param bool $isSortable
     * @param bool $isUsedForRules
     *
     * @return void
     * @throws \Exception
     */
    public function addSourceField(
        string $entityType,
        string $code,
        string $defaultLabel,
        string $type = 'text',
        bool $isSearchable = true,
        int $weight = 1,
        bool $isSpellchecked = false,
        bool $isFilterable = false,
        bool $isSortable = false,
        bool $isUsedForRules = false
    ): void {
        $sourceFieldData = [
            'metadata' => '/metadata/' . $this->metadata->getMetadataIdByEntityType($entityType),
            'code' => $code,
            'defaultLabel' => $defaultLabel,
            'type' => $type,
            'isSearchable' => $isSearchable,
            'weight' => $weight,
            'isSpellchecked' => $isSpellchecked,
            'isFilterable' => $isFilterable,
            'isSortable' => $isSortable,
            'isUsedForRules' => $isUsedForRules,
        ];
        try {
            // If id is found, the field exist on Gally side. We will update the field.
            $sourceFieldData['id'] = $this->getSourceFieldIdByCode($code, $entityType);
        } catch (\Exception $exception) {
            // Do nothing, it means we will create the field instead of updating it.
        }

        $this->createSourceField($sourceFieldData);
    }

    /**
     * @throws \Exception
     */
    public function getSourceFieldIdByCode($code, $entityType)
    {
        if (!isset($this->sourceFieldsByCode[$entityType][$code])) {
            throw new \Exception('Cannot find source field ' . $code . ' for entity type ' . $entityType);
        }

        return $this->sourceFieldsByCode[$entityType][$code]->getId();
    }

    /**
     * @throws \Exception
     */
    public function getSourceFieldOptionIdByCode($code, $sourceFieldId)
    {
        if (!isset($this->sourceFieldsOptionByCode[$sourceFieldId][$code])) {
            throw new \Exception('Cannot find source field option ' . $code . ' for source field ' . $sourceFieldId);
        }

        return $this->sourceFieldsOptionByCode[$sourceFieldId][$code]->getId();
    }

    /**
     * @throws \Exception
     */
    public function getSourceFieldOptionLabelId($localizedCatalogId, $sourceFieldId)
    {
        if (!isset($this->sourceFieldsOptionsLabelsById[$localizedCatalogId][$sourceFieldId])) {
            throw new \Exception('Cannot find source field option label for source field ' . $sourceFieldId . ' and catalog ' . $localizedCatalogId);
        }

        return $this->sourceFieldsOptionsLabelsById[$localizedCatalogId][$sourceFieldId]->getId();
    }

    /**
     * @throws \Exception
     */
    public function getSourceFieldLabelIdByCode($code, $entityType, $catalogId)
    {
        if (!isset($this->sourceFieldsLabelsByCode[$entityType][$catalogId][$code])) {
            throw new \Exception('Cannot find source field label for field ' . $code . ' for catalog ' . $catalogId . ' and entity type ' . $entityType);
        }

        return $this->sourceFieldsLabelsByCode[$entityType][$catalogId][$code]->getId();
    }

    private function getSourceFields(): void
    {
        $curPage = 1;

        do {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi[] $sourceFields */
            $sourceFields = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'getSourceFieldCollection',
                currentPage: $curPage,
                pageSize:    30
            );

            foreach ($sourceFields as $sourceField) {
                $metadata = str_replace('/metadata/', '', $sourceField->getMetadata());
                $entityType = $this->metadata->getMetadataEntityTypeById($metadata);
                $this->sourceFieldsByCode[$entityType][$sourceField->getCode()] = $sourceField;
                $this->sourceFieldsById[$sourceField->getId()]                  = $sourceField;
            }
            $curPage++;
        } while (count($sourceFields) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldLabel[] $sourceFieldLabels */
            $sourceFieldLabels = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'getSourceFieldLabelCollection',
                currentPage: $curPage,
                pageSize: 30
            );

            foreach ($sourceFieldLabels as $sourceFieldLabel) {
                $sourceFieldId = (int)str_replace(
                    '/source_fields/',
                    '',
                    $sourceFieldLabel->getSourceField()
                );
                $catalogId = (int)str_replace(
                    '/localized_catalogs/',
                    '',
                    $sourceFieldLabel->getLocalizedCatalog()
                );
                $sourceFieldCode = $this->sourceFieldsById[$sourceFieldId]->getCode(
                );
                $metadata = str_replace(
                    '/metadata/',
                    '',
                    $this->sourceFieldsById[$sourceFieldId]->getMetadata()
                );
                $entityType = $this->metadata->getMetadataEntityTypeById(
                    $metadata
                );
                $this->sourceFieldsLabelsByCode[$entityType][$catalogId][$sourceFieldCode] = $sourceFieldLabel;
            }
            ++$curPage;
        } while (\count($sourceFieldLabels) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldOption[] $sourceFieldOptions */
            $sourceFieldOptions = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionApi::class,
                'getSourceFieldOptionCollection',
                currentPage: $curPage,
                pageSize: 30
            );

            foreach ($sourceFieldOptions as $sourceFieldOption) {
                $sourceFieldId = (int)str_replace(
                    '/source_fields/',
                    '',
                    $sourceFieldOption->getSourceField()
                );
                $this->sourceFieldsOptionById[$sourceFieldId][$sourceFieldOption->getId()] = $sourceFieldOption;
                $this->sourceFieldsOptionByCode[$sourceFieldId][$sourceFieldOption->getCode()] = $sourceFieldOption;
            }
            ++$curPage;
        } while (\count($sourceFieldOptions) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldOptionLabel[] $sourceFieldOptionsLabels */
            $sourceFieldOptionsLabels = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionLabelApi::class,
                'getSourceFieldOptionLabelCollection',
                currentPage: $curPage,
                pageSize: 30
            );

            foreach ($sourceFieldOptionsLabels as $sourceFieldOptionLabel) {
                $sourceFieldOptionId = (int)str_replace(
                    '/source_field_options/',
                    '',
                    $sourceFieldOptionLabel->getSourceFieldOption()
                );
                if (method_exists($sourceFieldOptionLabel, 'getCatalog')) {
                    $catalogId = (int)str_replace('/localized_catalogs/', '', $sourceFieldOptionLabel->getCatalog());
                } else {
                    $catalogId = (int)str_replace(
                        '/localized_catalogs/',
                        '',
                        $sourceFieldOptionLabel->getLocalizedCatalog()
                    );
                }
                $this->sourceFieldsOptionsLabelsById[$catalogId][$sourceFieldOptionId] = $sourceFieldOptionLabel;
            }
            ++$curPage;
        } while (\count($sourceFieldOptionsLabels) > 0);
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldSourceFieldApi|null
     */
    private function createSourceField($data): ?\Gally\Rest\Model\SourceFieldSourceFieldApi
    {
        $input = new \Gally\Rest\Model\SourceFieldSourceFieldApi($data);
        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi $sourceField */
            $sourceField = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'patchSourceFieldItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi $sourceField */
            $sourceField = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'postSourceFieldCollection',
                $input
            );
        }

        return $sourceField;
    }

    /**
     * Create (ibexa) path source field on metadata.
     *
     * @throws \Exception
     */
    public function createPathSourceField(string $entityType): void
    {
        $attributeCode = 'path';

        $sourceFieldData = [
            'metadata' => '/metadata/' . $this->metadata->getMetadataIdByEntityType($entityType),
            'code' => $attributeCode,
            'defaultLabel' => 'path',
            'type' => 'text',
            'isSearchable' => false,
            'weight' => 1,
            'isSpellchecked' => false,
            'isFilterable' => true,
            'isSortable' => false,
            'isUsedForRules' => false,
        ];
        try {
            // If path is found, the field exist on Gally side. We will update the field.
            $sourceFieldData['path'] = $this->getSourceFieldIdByCode($attributeCode, $entityType);
        } catch (\Exception $exception) {
            // Do nothing, it means we will create the field instead of updating it.
        }

        $this->createSourceField($sourceFieldData);
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldLabel|null
     */
    private function createSourceFieldLabel($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldLabel($data);
        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldLabel $sourceFieldLabel */
            $sourceFieldLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'patchSourceFieldLabelItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldLabel $sourceFieldLabel */
            $sourceFieldLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'postSourceFieldLabelCollection',
                $input
            );
        }

        return $sourceFieldLabel;
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldOption|null
     */
    private function createSourceFieldOption($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldOption($data);
        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldOption $sourceFieldOption */
            $sourceFieldOption = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionApi::class,
                'patchSourceFieldOptionItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldOption $sourceFieldOption */
            $sourceFieldOption = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionApi::class,
                'postSourceFieldOptionCollection',
                $input
            );
        }

        return $sourceFieldOption;
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldOptionLabel|null
     */
    private function createSourceFieldOptionLabel($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldOptionLabel($data);
        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldOptionLabel $sourceFieldLabel */
            $sourceFieldOptionLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionLabelApi::class,
                'patchSourceFieldOptionLabelItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldOptionLabel $sourceFieldLabel */
            $sourceFieldOptionLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionLabelApi::class,
                'postSourceFieldOptionLabelCollection',
                $input
            );
        }

        return $sourceFieldOptionLabel;
    }

    public function getType(FieldDefinition $fieldDefinition)
    {
        $type = 'text';

        if ($fieldDefinition->fieldTypeIdentifier === 'ezinteger') {
            $type = 'int';
        }

        if ($fieldDefinition->fieldTypeIdentifier === 'ezselection') {
            $type = 'select';
        }

        if ($fieldDefinition->fieldTypeIdentifier === 'ezfloat') {
            $type = 'float';
        }

        return $type;
    }
}
