# Ibexa Gally

Ibexa Gally is a bundle that can index Ibexa contents in Gally to use the Gally searchandising engine.
You can choose which content types to index, content are auto index in Gally.

## Features

- Auto index content on Gally
- Search API that use Gally with Filter
- Choose the content type to index
- Mapping of parameters between Ibexa field and Gally sourcefield

### Features in coming

- Support multi-language for content

## Commands

- `gally:structure:update` Update or init the Gally with Ibexa contents types
- `gally:structure:purge` purge everything that update and reindex created
- `gally:index:reindex` Index or reindex Ibexa contents on Gally

## Search

Search example with a controller

```php
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Service\Search\Search;
use Smile\Ibexa\Gally\Api\Search\SearchFilter;

#[Route('/gally/search/{site}/{languageCode}/{entityType}/{text}', name: 'gally_test', methods: ['GET', 'POST'])]
    public function index(
        string $site,
        string $languageCode,
        string $entityType,
        string $text,
        Search $search,
        Catalog $catalog
    ): Response {
        $filter = new SearchFilter();
        $filter->setMatchFilter("path", "/67");
        echo '<pre>';
        print_r(
            $search->search(
                $catalog->getLocalizedCatalogByName($site, $languageCode),
                $text,
                $entityType,
                1,
                10
            )
        );
        echo '</pre>';
        exit;
    }
```

## Configuration

```yaml
# config/packages/ibexa_gally.yaml
ibexa_gally:
    credentials:
        # email for admin account
        email: example@example.com
        # password for admin account
        password: changeMe!
        # host of the Gally
        host: https://gally.local/
    curl_options:
        # Resolve for Gally in docker from Ibexa env in another docker
        # this ip is get from : ip addr show docker0
        curl_resolve: gally.local:443:172.16.0.1
    debug: false
    # Parameters for content to index
    indexable_content:
        # the content types to index
        # equivalent to metadata in Gally
        content_types:
            - test_page
        # the ibexa field type to index from content type
        # equivalent to source field type in Gally
        field_types:
            - ezstring
            - ezrichtext
            - eztext
    # Mapping between Ibexa fields and Gally source field configurations
    # identifier : the Ibexa field identifier
    # This examples use the default values
    source_field_mapping:
        - { identifier: title, isSearchable: true, weight: 1, isSpellchecked: false, isFilterable: false, isSortable: false, isUsedForRules: false }
```

