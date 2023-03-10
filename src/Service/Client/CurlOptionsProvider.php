<?php

namespace Smile\Ibexa\Gally\Service\Client;

class CurlOptionsProvider
{
    public function __construct(private readonly array $options)
    {
    }

    private function getCurlResolve(): string
    {
        return $this->options['curl_resolve'];
    }

    public function getOptions(): array
    {
        /*
         * CURLOPT_RESOLVE is here for connection between docker (ibexa docker <=> gally docker)
         * using gally.local address on host computer
         */
        return [
            'verify' => false,
            'curl' => [\CURLOPT_RESOLVE => [$this->getCurlResolve()]],
        ];
    }
}
