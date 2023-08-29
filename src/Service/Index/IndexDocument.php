<?php

namespace Smile\Ibexa\Gally\Service\Index;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Ibexa\Contracts\Core\FieldType\Value;
use Ibexa\Contracts\Core\Persistence\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidCriterionArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\Repository\SiteAccessAware\ContentService;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
use stdClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexDocument
{

    public function __construct(
        private readonly Index $index,
        private readonly Catalog $catalog,
        private readonly IndexableContentProvider $indexableContentProvider,
        private readonly ContentTypeService $contentTypeService,
        private readonly SearchService $searchService,
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
        private readonly ContainerInterface $container,
        private readonly ConfigResolverInterface $configResolver,
        private readonly Connection $connection,
    ) {
    }

    /**
     * [Re]Index all contents
     *
     * @param callable $logFunction
     *
     * @return void
     * @throws InvalidCriterionArgumentException
     * @throws InvalidArgumentException
     * @throws BadStateException
     * @throws UnauthorizedException
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception|NotFoundException
     */
    public function reindexAll(callable $logFunction): void
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();
        $contentTypesConfigSQL = [];
        foreach ($contentTypesConfig as $contentType) {
            $contentTypesConfigSQL[] = "'".$contentType."'";
        }

        $localizedCatalogs = $this->catalog->getLocalizedCatalogsId();
        $localizedCatalogsCode = $this->catalog->getLocalizedCatalogsCode();

        $indexList = [];
        foreach ($localizedCatalogs as $key => $localizedCatalog) {
            $indexes = [];
            $logFunction(
                'Start indexing for catalog '.$localizedCatalogsCode[$key],
                OutputInterface::VERBOSITY_VERBOSE
            );
            $catalogCode = explode('_', $localizedCatalogsCode[$key]);
            $code = $catalogCode[1];
            $catalog = $catalogCode[0];

            $siteaccessName = substr($localizedCatalogsCode[$key], 0, -7);
            $siteaccessLocationId = $this->configResolver->getParameter(
                'content.tree_root.location_id',
                null,
                $siteaccessName
            );
            try {
                $subtree = $this->locationService->loadLocation($siteaccessLocationId)->pathString;
            } catch (NotFoundException|UnauthorizedException $e) {
                dump($e);
                $logFunction("ERROR, Skipping . ".$localizedCatalogsCode[$key]);
                continue;
            }

            $logFunction('Load content type groups', OutputInterface::VERBOSITY_VERBOSE);
            $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();
            foreach ($contentTypeGroups as $contentTypeGroup) {
                $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);

                foreach ($contentTypes as $contentType) {
                    if (!in_array($contentType->identifier, $contentTypesConfig)) {
                        continue;
                    }

                    // Create Index on metadata
                    $logFunction(
                        'Create index of '.$contentType->identifier
                    );
                    $index = $this->index->createIndex($contentType->identifier, $localizedCatalog);
                    $indexes[$contentType->identifier] = $index['name'];
                }
            }
            $indexList[$localizedCatalogsCode[$key]] = [
                "index" => $indexes,
                "subtree" => $subtree,
                "code" => $code,
                "contents" => [],
            ];
        }

        $query = $this->connection->createQueryBuilder();
        $expr = $query->expr();
        $query
            ->select('COUNT(c.id)')
            ->from('ezcontentobject', 'c')
            ->join('c', 'ezcontentclass', 'cl', 'cl.id = c.contentclass_id')
            ->where('c.status = :status')
            ->andWhere($expr->in('cl.identifier', $contentTypesConfigSQL))
            ->setParameter('status', ContentInfo::STATUS_PUBLISHED, ParameterType::INTEGER);
        $maxCount = $query->execute()->fetchOne();
        $logFunction($maxCount." contents to index.");

        $query = $this->connection->createQueryBuilder();
        $expr = $query->expr();
        $query
            ->select('c.id')
            ->from('ezcontentobject', 'c')
            ->join('c', 'ezcontentclass', 'cl', 'cl.id = c.contentclass_id')
            ->where('c.status = :status')
            ->andWhere($expr->in('cl.identifier', $contentTypesConfigSQL))
            ->setParameter('status', ContentInfo::STATUS_PUBLISHED, ParameterType::INTEGER);

        $statement = $query->execute()->getIterator();

        $count = 0;
        foreach ($statement as $item) {
            $content = null;
            try {
                $content = $this->contentService->loadContent($item["id"]);
                $logFunction("$count/$maxCount - Indexing ".$content->getName());
                $count++;
                foreach ($indexList as $key => $indexes) {
                    foreach ($indexes as $index) {
                        if (str_contains(
                            $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString,
                            $indexes["subtree"]
                        )) {
                            $contentType = $content->getContentType()->identifier;
                            $indexList[$key]["contents"][$contentType][] = $content;
                        }
                    }
                }
            } catch (\Ibexa\Core\Base\Exceptions\UnauthorizedException $exception) {
                dump($exception);
            }
            if ($count % 100 === 0) {
                $logFunction("Sending last 100 content before continuing");
                $sending = 0;
                foreach ($indexList as $indexes) {
                    $logFunction("$sending/".count($indexList)." sending...");
                    $sending++;
                    foreach ($indexes["index"] as $index) {
                        foreach ($contentTypesConfig as $contentType) {
                            if (empty($indexes["contents"][$contentType])) {
                                $logFunction("Skipping $contentType on $index because no contents...");
                                continue;
                            }

                            $logFunction(
                                'Sending '.count(
                                    $indexes["contents"][$contentType]
                                ).' contents '.$contentType." to $index"
                            );
                            $this->sendContentsToIndex(
                                $index,
                                $indexes["contents"][$contentType],
                                $indexes["code"]
                            );
                        }
                    }
                }
                foreach ($indexList as $key => $indexes) {
                    foreach ($contentTypesConfig as $contentType) {
                        unset($indexList[$key]["contents"][$contentType]);
                        $indexList[$key]["contents"][$contentType] = [];
                    }
                }
            }
        }
        $logFunction("Final sending to gally");

        foreach ($indexList as $indexes) {
            foreach ($indexes["index"] as $index) {
                foreach ($contentTypesConfig as $contentType) {
                    if (empty($indexes["contents"][$contentType])) {
                        $logFunction("Skipping $contentType on $index because no contents...");
                        continue;
                    }

                    $logFunction('Sent '.count($indexes["contents"][$contentType]).' contents '.$contentType);
                    $this->sendContentsToIndex($index, $indexes["contents"][$contentType], $indexes["code"]);

                    $logFunction(
                        'Install index '.$index
                    );
                    $result = $this->index->installIndex($index);
                    $logFunction(
                        'Result '.$result
                    );

                    $logFunction(
                        'Refresh index '.$index
                    );
                    $result = $this->index->refreshIndex($index);
                    $logFunction(
                        'Result '.$result
                    );
                }
            }
        }
    }

    /**
     * Send an ibexa content array of one content type to Gally.
     *
     * @param string $indexName indexName
     * @param Content[] $contents
     * @param string|null $languageCode if null default language of content
     * @return mixed
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function sendContentsToIndex(string $indexName, array $contents, string $languageCode = null): mixed
    {
        $fieldTypeConfig = $this->indexableContentProvider->getFieldTypes();

        $documents = [];
        foreach ($contents as $content) {
            if ($languageCode === null) {
                $languageCode = $content->getDefaultLanguageCode();
            }
            if ($content->getVersionInfo()->getContentInfo()->isHidden) {
                continue;
            }
            $obj = new stdClass();
            $obj->id = $content->id;
            $obj->path = $this->loadLocations($content->id);
            foreach ($content->getFieldsByLanguage($languageCode) as $fieldDefinition) {
                if (in_array($fieldDefinition->fieldTypeIdentifier, $fieldTypeConfig)) {
                    $typeIdentifier = $fieldDefinition->fieldDefIdentifier;
                    $value = $content->getFieldValue($typeIdentifier, $languageCode);
                    $obj = $this->getValueForGally($obj, $value, $typeIdentifier);
                }
            }
            $documents[] = json_encode($obj);
        }

        return $this->index->sendData($indexName, $documents);
    }

    /**
     * Charge le pathstring de toutes les locations d'un contenu
     *
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    private function loadLocations(int $contentId): array
    {
        $array = [];
        $contentInfo = $this->contentService->loadContentInfo($contentId);
        foreach ($this->locationService->loadLocations($contentInfo) as $location) {
            $array[] = $location->pathString;
        }

        return $array;
    }

    /**
     * Transform Ibexa value to a Gally acceptable value
     *
     * @param stdClass $obj Ibexa FieldType Value
     * @param Value $value Value
     * @param string $typeIdentifier the field of the object to pass the value
     * @return stdClass object with the value transformed
     */
    private function getValueForGally(
        stdClass $obj,
        Value $value,
        string $typeIdentifier
    ): stdClass {
        $class = get_class($value);
        switch ($class) {
            case 'Ibexa\Core\FieldType\Date\Value':
                /** @var \Ibexa\Core\FieldType\Date\Value $value */
                $date = $value->date->format('Y-m-d H:i:s');
                $obj->$typeIdentifier = $date;
                break;
            case 'Ibexa\Core\FieldType\DateAndTime\Value':
                /** @var \Ibexa\Core\FieldType\DateAndTime\Value $value */
                if ($value->value === null) {
                    break;
                }
                $datetime = $value->value->format('Y-m-d H:i:s');
                $obj->$typeIdentifier = $datetime;
                break;
            case 'Ibexa\Core\FieldType\Float\Value':
                /** @var \Ibexa\Core\FieldType\Float\Value $value */
                $float = floatval($value->value);
                $obj->$typeIdentifier = $float;
                break;
            case 'Ibexa\Core\FieldType\Integer\Value':
                /** @var \Ibexa\Core\FieldType\Integer\Value $value */
                $int = intval($value->value);
                $obj->$typeIdentifier = $int;
                break;
            case 'Ibexa\Core\FieldType\Checkbox\Value':
                /** @var \Ibexa\Core\FieldType\Checkbox\Value $value */
                $bool = $value->bool;
                $obj->$typeIdentifier = $bool;
                break;
            case 'Ibexa\Core\FieldType\TextLine\Value':
            case 'Ibexa\Core\FieldType\TextBlock\Value':
                /** @var \Ibexa\Core\FieldType\TextBlock\Value $value */
                $text = $value->text;
                $obj->$typeIdentifier = $text;
                break;
            case 'Ibexa\FieldTypeRichText\FieldType\RichText\Value':
                /** @var \Ibexa\FieldTypeRichText\FieldType\RichText\Value $value */
                $obj->$typeIdentifier = $value->xml->textContent;
                break;
            default:
        }

        return $obj;
    }

    /**
     * Index a subtree of contents
     *
     * @param string $subtree subtree path
     * @return void
     * @throws UnauthorizedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws BadStateException
     */
    public function indexSubtree(string $subtree): void
    {
        $query = new LocationQuery();
        $query->query = new Criterion\LogicalAnd(
            [
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Subtree($subtree),
            ]
        );
        $count = $this->searchService->findLocations($query)->totalCount;
        $query->limit = $count;
        $results = $this->searchService->findLocations($query);
        foreach ($results->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            $content = $location->getContent();
            $this->sendContentWithId($content->id);
        }
    }

    /**
     * Index one content with its id
     *
     * @param int $id content id
     * @param array|null $languageCodes if null every language of the content
     * @return void
     * @throws UnauthorizedException
     * @throws BadStateException
     * @throws NotFoundException
     */
    public function sendContentWithId(int $id, array $languageCodes = null): void
    {
        $content = $this->contentService->loadContent(
            $id,
            $languageCodes
        );
        $this->sendContent($content);
    }

    /**
     * Index one Content on its site access
     * Index only content set in the config
     *
     * @param Content $content content
     * @param array|null $languageCodes if null every language of the content
     *
     * @return void
     *
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function sendContent(Content $content, array $languageCodes = null): void
    {
        if ($this->checkContentIndexable($content->getContentType()->identifier)) {
            if ($languageCodes === null) {
                $languageCodes = $content->getVersionInfo()->languageCodes;
            }
            $siteAccessesToIndex = $this->getSiteAccessFromContent(
                $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString
            );

            $contentType = $content->getContentType();

            foreach ($siteAccessesToIndex as $siteAccess) {
                foreach ($languageCodes as $language) {
                    $indexName = 'gally_'.$siteAccess.'_'.strtolower($language).'_'.$contentType->identifier;
                    $this->sendContentsToIndex($indexName, [$content], $language);
                }
            }
        }
    }

    /**
     * Check if a content type is wanted to index
     *
     * @param string $identifier content type identifier
     * @return bool
     */
    private function checkContentIndexable(string $identifier): bool
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();
        if (in_array($identifier, $contentTypesConfig)) {
            return true;
        }

        return false;
    }

    /**
     * Get site access of a content
     * It check every subtree of site access
     * @param string $pathString path content
     * @return array
     */
    private function getSiteAccessFromContent(string $pathString): array
    {
        $siteAccessList = $this->container->getParameter('ibexa.site_access.list');
        $siteAccessGroups = $this->container->getParameter('ibexa.site_access.groups');
        $contentSubtree = $pathString;
        $siteAccessesToIndex = [];
        foreach ($siteAccessGroups as $group => $siteAccessFromGroup) {
            if ($this->container->hasParameter("ibexa.site_access.config.$group.subtree_paths.content")) {
                $subtree = $this->container->getParameter("ibexa.site_access.config.$group.subtree_paths.content");
                if (str_contains($contentSubtree, $subtree)) {
                    foreach ($siteAccessFromGroup as $siteAccess) {
                        $siteAccessesToIndex[] = $siteAccess;
                    }
                }
            }
        }
        foreach ($siteAccessList as $siteAccess) {
            if ($this->container->hasParameter("ibexa.site_access.config.$siteAccess.subtree_paths.content")) {
                $subtree = $this->container->getParameter(
                    "ibexa.site_access.config.$siteAccess.subtree_paths.content"
                );
                if (str_contains($contentSubtree, $subtree)) {
                    $siteAccessesToIndex[] = $siteAccess;
                }
            }
        }

        return $siteAccessesToIndex;
    }

    /**
     * Delete a content fromm gally with id and language
     *
     * @param int $id
     * @param array|null $languageCodes
     * @return void
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function deleteContent(int $id, array $languageCodes = null): void
    {
        $content = $this->contentService->loadContent(
            $id,
            $languageCodes
        );
        if ($this->checkContentIndexable($content->getContentType()->identifier)) {
            $siteAccessesToIndex = $this->getSiteAccessFromContent(
                $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString
            );

            $contentType = $content->getContentType();

            $documents[] = json_encode($id);

            if ($languageCodes === null) {
                $languageCodes = $content->getVersionInfo()->languageCodes;
            }

            foreach ($siteAccessesToIndex as $siteAccess) {
                foreach ($languageCodes as $language) {
                    $indexName = 'gally_'.$siteAccess.'_'.strtolower($language).'_'.$contentType->identifier;
                    $this->index->deleteData($indexName, $documents);
                }
            }
        }
    }
}
