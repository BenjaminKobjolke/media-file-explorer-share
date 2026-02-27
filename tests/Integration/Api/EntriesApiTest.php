<?php
declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Actions\DatabaseAction;
use Tests\Integration\ApiTestCase;

class EntriesApiTest extends ApiTestCase
{
    public function testGetEntryById(): void
    {
        $id = $this->seedTextEntry('Hello', 'World');

        $request = $this->createJsonRequest('GET', "/entries/{$id}");
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame($id, $data['id']);
        $this->assertSame('Hello', $data['subject']);
        $this->assertArrayHasKey('attachments', $data);
    }

    public function testGetEntryByIdReturns404(): void
    {
        // Seed to init DB, then request nonexistent
        $this->seedTextEntry();
        $request = $this->createJsonRequest('GET', '/entries/999');
        $response = $this->runRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPostEntriesLookup(): void
    {
        $id = $this->seedTextEntry('Via POST', 'Body');

        $request = $this->createJsonRequest('POST', '/entries', ['id' => $id]);
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Via POST', $data['subject']);
    }

    public function testPostEntriesWithoutIdReturns400(): void
    {
        $request = $this->createJsonRequest('POST', '/entries', ['foo' => 'bar']);
        $response = $this->runRequest($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testGetEntriesPaginated(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedTextEntry("Entry {$i}");
        }

        $request = $this->createJsonRequest('GET', '/entries?page=1&per_page=2');
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame(5, $data['total']);
        $this->assertCount(2, $data['entries']);
    }

    public function testGetEntriesWithFieldFilter(): void
    {
        $id = $this->seedTextEntry('Tagged');
        $this->seedTextEntry('Untagged');

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];
        DatabaseAction::setEntryFieldValue($this->dbPath, $id, 'status', $openId);

        $request = $this->createJsonRequest('GET', "/entries?status_id={$openId}");
        $response = $this->runRequest($request);

        $data = $this->decodeResponse($response);
        $this->assertSame(1, $data['total']);
        $this->assertSame('Tagged', $data['entries'][0]['subject']);
    }

    public function testPutEntryUpdatesSubject(): void
    {
        $id = $this->seedTextEntry('Old Subject', 'Body');

        $request = $this->createJsonRequest('PUT', "/entries/{$id}", ['subject' => 'New Subject']);
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('New Subject', $data['subject']);
    }

    public function testPutEntryWithFieldValue(): void
    {
        $id = $this->seedTextEntry('Status test');

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $closedId = $options[2]['id']; // 'closed'

        $request = $this->createJsonRequest('PUT', "/entries/{$id}", ['status_id' => $closedId]);
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame($closedId, $data['status_id']);
    }

    public function testPutEntryNoFieldsReturns400(): void
    {
        $id = $this->seedTextEntry();
        $request = $this->createJsonRequest('PUT', "/entries/{$id}", []);
        $response = $this->runRequest($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteEntry(): void
    {
        $id = $this->seedTextEntry('To delete');

        $request = $this->createJsonRequest('DELETE', "/entries/{$id}");
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Entry deleted', $data['message']);

        // Verify gone
        $request = $this->createJsonRequest('GET', "/entries/{$id}");
        $response = $this->runRequest($request);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteEntryReturns404(): void
    {
        $this->seedTextEntry();
        $request = $this->createJsonRequest('DELETE', '/entries/999');
        $response = $this->runRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteAttachment(): void
    {
        $id = $this->seedTextEntry('Parent');
        $ctx = $this->makeContext();
        $attId = DatabaseAction::appendText($this->dbPath, $id, 'Att', 'Body', $ctx);

        $request = $this->createJsonRequest('DELETE', "/attachments/{$attId}");
        $response = $this->runRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Attachment deleted', $data['message']);
    }
}
