<?php

namespace Smile\Ibexa\Gally\Service\Client;

use Gally\Rest\ApiException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;

class Client
{
    private ?string $token = null;

    public function __construct(
        private readonly CredentialProvider $credentialProvider,
        private readonly CurlOptionsProvider $curlOptionsProvider,
        private readonly Authentication $authenticationProvider,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     */
    public function getAuthorizationToken(): ?string
    {
        if (null === $this->token) {
            $this->token = $this->authenticationProvider->getAuthenticationToken();
        }

        return $this->token;
    }

    public function query($endpoint, $operation, ...$input)
    {
        $config = null;
        try {
            $config = \Gally\Rest\Configuration::getDefaultConfiguration()->setApiKey(
                'Authorization',
                $this->getAuthorizationToken()
            )->setApiKeyPrefix(
                'Authorization',
                'Bearer'
            )->setHost(trim($this->credentialProvider->getHost(), '/'));
        } catch (\Exception | GuzzleException $e) {
            $this->logger->info(\get_class($e) . " when calling {$endpoint}->{$operation}: " . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
            $this->logger->info('Input was');
            $this->logger->info(print_r($input, true));
        }
        $apiInstance = new $endpoint(
            new \GuzzleHttp\Client($this->curlOptionsProvider->getOptions()),
            $config
        );

        try {
            if ($this->debug === true) {
                $this->logger->info("Calling {$endpoint}->{$operation} : ");
                $this->logger->info(print_r($input, true));
            }
            $result = $apiInstance->$operation(...$input);
            if ($this->debug === true) {
                $this->logger->info("Result of {$endpoint}->{$operation} : ");
                $this->logger->info(print_r($result, true));
            }
        } catch (\Exception $e) {
            $this->logger->info(\get_class($e) . " when calling {$endpoint}->{$operation}: " . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
            $this->logger->info('Input was');
            $this->logger->info(print_r($input, true));
            $result = null;
        }

        return $result;
    }

    /**
     * Make graphql query on Gally.
     *
     * @param string $query GraphQL query
     * @param string $variables Query variables
     *
     * @throws GuzzleException
     * @throws ApiException
     *
     * @return string JSON result
     */
    public function graphqlQuery(string $query, string $variables): string
    {
        $client = new \GuzzleHttp\Client($this->curlOptionsProvider->getOptions());
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
            'Content-Type' => 'application/json',
        ];
        $body = json_encode(['query' => $query, 'variables' => $variables]);
        $request = new Request('POST', $this->credentialProvider->getHost() . 'graphql', $headers, $body);
        $res = $client->sendAsync($request)->wait();

        return $res->getBody();
    }
}
