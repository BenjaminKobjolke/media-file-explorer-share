<?php
declare(strict_types=1);

namespace Tests\Integration\Api;

use Tests\Integration\ApiTestCase;

class CustomFieldsApiTest extends ApiTestCase
{
    public function testListCustomFields(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('GET', '/custom-fields');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertGreaterThanOrEqual(3, count($data)); // project, status, resolution
    }

    public function testCreateCustomField(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('POST', '/custom-fields', [
            'name' => 'priority',
            'description' => 'Bug priority',
            'sort_order' => 5,
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('priority', $data['name']);
    }

    public function testCreateDuplicateFieldReturns409(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('POST', '/custom-fields', [
            'name' => 'status',
            'description' => 'Duplicate',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testCreateFieldWithInvalidNameReturns400(): void
    {
        $request = $this->createJsonRequest('POST', '/custom-fields', [
            'name' => 'Invalid Name!',
            'description' => 'Bad',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateCustomField(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('PUT', '/custom-fields/status', [
            'description' => 'Updated description',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Updated description', $data['description']);
    }

    public function testUpdateMissingFieldReturns404(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('PUT', '/custom-fields/nonexistent', [
            'description' => 'No such field',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteCustomField(): void
    {
        // Init DB and create a field to delete
        $this->seedTextEntry();

        $create = $this->createJsonRequest('POST', '/custom-fields', [
            'name' => 'deleteme',
            'description' => 'Temporary',
        ]);
        $this->runRequest($create);

        $request = $this->createJsonRequest('DELETE', '/custom-fields/deleteme');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Custom field deleted', $data['message']);
    }
}
