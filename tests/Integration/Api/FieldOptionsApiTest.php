<?php
declare(strict_types=1);

namespace Tests\Integration\Api;

use Tests\Integration\ApiTestCase;

class FieldOptionsApiTest extends ApiTestCase
{
    public function testListFieldOptions(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('GET', '/field-options/status');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertCount(3, $data); // open, in progress, closed
    }

    public function testListOptionsForMissingFieldReturns404(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('GET', '/field-options/nonexistent');
        $response = $this->runRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCreateFieldOption(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('POST', '/field-options/project', [
            'name' => 'Alpha Project',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Alpha Project', $data['name']);
    }

    public function testCreateDuplicateOptionReturns409(): void
    {
        // Init DB
        $this->seedTextEntry();

        $request = $this->createJsonRequest('POST', '/field-options/status', [
            'name' => 'open',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testUpdateFieldOption(): void
    {
        // Init DB
        $this->seedTextEntry();

        // Create an option to rename
        $create = $this->createJsonRequest('POST', '/field-options/project', ['name' => 'OldName']);
        $createResp = $this->runRequest($create);
        $createData = $this->decodeResponse($createResp);
        $optId = $createData['id'];

        $request = $this->createJsonRequest('PUT', "/field-options/project/{$optId}", [
            'name' => 'NewName',
        ]);
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('NewName', $data['name']);
    }

    public function testDeleteFieldOption(): void
    {
        // Init DB
        $this->seedTextEntry();

        // Create then delete
        $create = $this->createJsonRequest('POST', '/field-options/project', ['name' => 'ToDelete']);
        $createResp = $this->runRequest($create);
        $createData = $this->decodeResponse($createResp);
        $optId = $createData['id'];

        $request = $this->createJsonRequest('DELETE', "/field-options/project/{$optId}");
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Option deleted', $data['message']);
    }
}
