<?php

namespace Smile\Ibexa\Gally\Service\Client;

class CredentialProvider
{
    public function __construct(private readonly array $credentials)
    {
    }

    public function getEmail(): string
    {
        return $this->credentials['email'];
    }

    public function getPassword(): string
    {
        return $this->credentials['password'];
    }

    public function getHost(): string
    {
        return $this->credentials['host'];
    }
}
