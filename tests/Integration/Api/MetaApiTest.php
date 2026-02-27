<?php
declare(strict_types=1);

namespace Tests\Integration\Api;

use Tests\Integration\ApiTestCase;

class MetaApiTest extends ApiTestCase
{
    public function testGetAuthReturnsNoneWhenDisabled(): void
    {
        $request = $this->createJsonRequest('GET', '/auth');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('none', $data['method']);
    }

    public function testGetAuthReturnsBasicWhenEnabled(): void
    {
        $config = $this->config;
        $config['auth_enabled'] = true;
        $this->app = $this->createApp($config);

        $request = $this->createJsonRequest('GET', '/auth');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('basic', $data['method']);
    }

    public function testGetFieldsIncludesReservedFields(): void
    {
        $request = $this->createJsonRequest('GET', '/fields');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $names = array_column($data, 'name');
        $this->assertContains('_id', $names);
        $this->assertContains('_email', $names);
    }

    public function testGetFieldsIncludesCustomFieldsWhenDbEnabled(): void
    {
        $request = $this->createJsonRequest('GET', '/fields');
        $response = $this->runRequest($request);

        $data = $this->decodeResponse($response);
        $names = array_column($data, 'name');
        $this->assertContains('_status', $names);
        $this->assertContains('_project', $names);
        $this->assertContains('_resolution', $names);
    }
}
