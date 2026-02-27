<?php
declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Actions\DatabaseAction;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Integration\ApiTestCase;

class UploadApiTest extends ApiTestCase
{
    public function testUploadTextCreatesEntry(): void
    {
        $payload = json_encode(['text_or_url' => 'Hello from test']);
        $stream = (new StreamFactory())->createStream($payload);
        $request = (new RequestFactory())->createRequest('POST', '/upload')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->runRequest($request);
        $this->assertSame(200, $response->getStatusCode());

        $insertId = (int) (string) $response->getBody();
        $this->assertGreaterThan(0, $insertId);

        $entry = DatabaseAction::getById($this->dbPath, $insertId);
        $this->assertNotNull($entry);
        $this->assertSame('text', $entry['type']);
    }

    public function testUploadEmptyBodyReturns400(): void
    {
        $stream = (new StreamFactory())->createStream('');
        $request = (new RequestFactory())->createRequest('POST', '/upload')
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($stream);

        $response = $this->runRequest($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUploadTextAppendMode(): void
    {
        $parentId = $this->seedTextEntry('Parent Entry');

        $payload = json_encode(['_id' => $parentId, 'text_or_url' => 'Appended note']);
        $stream = (new StreamFactory())->createStream($payload);
        $request = (new RequestFactory())->createRequest('POST', '/upload')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->runRequest($request);
        $this->assertSame(200, $response->getStatusCode());

        // Should return the parent ID
        $returnedId = (int) (string) $response->getBody();
        $this->assertSame($parentId, $returnedId);

        // Verify attachment created
        $atts = DatabaseAction::getAttachments($this->dbPath, $parentId);
        $this->assertCount(1, $atts);
    }

    public function testUploadTextWithCustomField(): void
    {
        $this->seedTextEntry(); // Init DB to create tables

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];

        $payload = json_encode(['text_or_url' => 'With status', '_status' => $openId]);
        $stream = (new StreamFactory())->createStream($payload);
        $request = (new RequestFactory())->createRequest('POST', '/upload')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->runRequest($request);
        $this->assertSame(200, $response->getStatusCode());

        $insertId = (int) (string) $response->getBody();
        $values = DatabaseAction::getEntryFieldValues($this->dbPath, $insertId);
        $this->assertSame($openId, $values['status']);
    }
}
