<?php

namespace Smile\Ibexa\Gally\Api\Search;

use Gally\Rest\ApiException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Client\Client;
use Smile\Ibexa\Gally\Service\Search\Filters\Filter;

class Search
{
    /**
     * Create graphql query with filter if needed.
     *
     * @param Filter[] $filters the filter
     *
     * @return string the query
     */
    private function getGraphqlSearchQuery(array $filters = []): string
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
        if (!empty($filters)) {
            $graphql .= <<<GRAPHQL
            filter: {

            GRAPHQL;

            foreach ($filters as $filter) {
                $graphql .= $filter->toGraphQL();
            }

            $graphql .= <<<GRAPHQL
            }
            GRAPHQL;
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
     * @param Filter[] $filters you need to build the filter before
     *
     * @return mixed
     */
    public function search(
        array $variables,
        array $filters = []
    ): mixed {
        $response = null;
        try {
            $response = $this->clientProvider->graphqlQuery(
                $this->getGraphqlSearchQuery($filters),
                json_encode($variables)
            );
        } catch (ApiException | GuzzleException $e) {
            $this->logger->info(\get_class($e) . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
        }

        return json_decode($response, true);
    }
}
