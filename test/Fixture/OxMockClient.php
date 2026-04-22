<?php

declare(strict_types=1);

namespace Horde\OpenXchange\Test\Fixture;

use Horde_Http_Client;
use Horde_Http_Request_Mock;

class OxMockClient
{
    private Horde_Http_Request_Mock $mock;
    private Horde_Http_Client $client;

    public function __construct()
    {
        $this->mock = new Horde_Http_Request_Mock();
        $this->client = new Horde_Http_Client(['request' => $this->mock]);
    }

    public function getClient(): Horde_Http_Client
    {
        return $this->client;
    }

    public function addJsonResponse(
        array $data,
        int $code = 200,
        array $headers = [],
    ): void {
        $this->mock->addResponse(
            json_encode($data),
            $code,
            '',
            $headers,
        );
    }

    public function addLoginResponse(string $session = 'test-session-id'): void
    {
        $this->addJsonResponse(['session' => $session]);
    }

    public function addRawResponse(
        string $body,
        int $code = 200,
        array $headers = [],
    ): void {
        $this->mock->addResponse($body, $code, '', $headers);
    }

    public function addErrorResponse(string $error, array $extra = []): void
    {
        $this->addJsonResponse(array_merge(['error' => $error], $extra));
    }
}
