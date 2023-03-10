<?php

namespace Smile\Ibexa\Gally\Service\Client;

use Gally\Rest\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use LogicException;

class Authentication
{
    private ?Client $client = null;

    public function __construct(
        private readonly CredentialProvider $credentialProvider,
        private readonly CurlOptionsProvider $curlOptionsProvider,
    ) {
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(
                $this->curlOptionsProvider->getOptions()
            );
        }
        return $this->client;
    }

    /**
     * Get JWT token for using the Gally API.
     *
     * @return string
     * @throws LogicException|GuzzleException
     *
     * @throws ApiException
     */
    public function getAuthenticationToken(): string
    {
        $resourcePath = '/authentication_token';
        $body = [
            'email' => $this->credentialProvider->getEmail(),
            'password' => $this->credentialProvider->getPassword(),
        ];
        $httpBody = \GuzzleHttp\Utils::jsonEncode($body);

        $request = new Request(
            'POST',
            trim($this->credentialProvider->getHost(), '/') . $resourcePath,
            [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            $httpBody
        );

        try {
            $responseJson = $this->getClient()->send($request);
        } catch (\Exception $e) {
            throw new ApiException(
                "[{$e->getCode()}] {$e->getMessage()}",
                $e->getCode(),
                $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            );
        }

        try {
            $response = \GuzzleHttp\Utils::jsonDecode($responseJson->getBody()->getContents());

            return (string)$response->token;
        } catch (\Exception $e) {
            throw new LogicException('Unable to fetch authorization token from Api response.');
        }
    }
}
