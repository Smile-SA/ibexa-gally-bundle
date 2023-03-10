<?php

namespace Smile\Ibexa\Gally\Api\Search;

use Gally\Rest\ApiException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Client\Client;

class Search
{
    /**
     * Create graphql query with filter if needed.
     *
     * @param SearchFilter|null $filter the filter
     *
     * @return string the query
     */
    private function getGraphqlSearchQuery(SearchFilter $filter = null): string
    {
        $graphql = <<<'GRAPHQL'
                    query getDocuments(
                        $entityType: String!,
                        $localizedCatalog: String!,
                        $currentPage: Int,
                        $pageSize: Int,
                        $search: String,
                        $sort: SortInput,
                    ) {
                        documents(
                            entityType: $entityType,
                            localizedCatalog: $localizedCatalog,
                            currentPage: $currentPage,
                            pageSize: $pageSize,
                            search: $search,
                            sort: $sort,
            GRAPHQL;
        // add filter if there is any
        if ($filter != null) {
            $strFilter = $filter->toGraphQL();
            $graphql .= $strFilter;
        }
        $graphql .= <<<'GRAPHQL'
                ) {
            		collection {
            			...on Document {
            				id
            				score
            				source
            			}
            		}
            		paginationInfo {
            			lastPage
                                    itemsPerPage
                                    totalCount
            		}
            		sortInfo {
            			current {
            				field
                                            direction
            			}
            		}
            		aggregations {
            			field
            			label
            			type
            			options {
            				count
            				label
            				value
            			}
            			hasMore
            		}
            	}
            }
            GRAPHQL;

        return $graphql;
    }

    public function __construct(
        private readonly Client $clientProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Search method.
     *
     * @param array $variables
     * @param SearchFilter|null $filter you need to build the filter before
     *
     * @return mixed
     */
    public function search(
        array $variables,
        SearchFilter $filter = null
    ): mixed {
        $response = null;
        try {
            $response = $this->clientProvider->graphqlQuery(
                $this->getGraphqlSearchQuery($filter),
                json_encode($variables)
            );
        } catch (ApiException | GuzzleException $e) {
            $this->logger->info(\get_class($e) . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
        }

        return json_decode($response, true);
    }
}
