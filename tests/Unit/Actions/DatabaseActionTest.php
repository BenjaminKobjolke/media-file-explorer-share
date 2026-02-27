<?php
declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\DatabaseAction;
use Tests\TestCase;

class DatabaseActionTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbPath = $this->createTempDbPath();
    }

    // -- Entry CRUD -----------------------------------------------------------

    public function testSaveTextReturnsInsertId(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Subject', 'Body text', $ctx);
        $this->assertSame(1, $id);
    }

    public function testSaveTextStoresCorrectData(): void
    {
        $ctx = $this->makeContext('10.0.0.1', 'Bot/2.0', '2025-06-15T12:00:00+00:00');
        DatabaseAction::saveText($this->dbPath, 'My Subject', 'My Body', $ctx);

        $row = DatabaseAction::getById($this->dbPath, 1);
        $this->assertNotNull($row);
        $this->assertSame('text', $row['type']);
        $this->assertSame('My Subject', $row['subject']);
        $this->assertSame('My Body', $row['body']);
        $this->assertSame('10.0.0.1', $row['ip']);
        $this->assertSame('Bot/2.0', $row['ua']);
        $this->assertSame('2025-06-15T12:00:00+00:00', $row['created_at']);
        $this->assertNull($row['filename']);
        $this->assertNull($row['file_path']);
        $this->assertNull($row['file_size']);
    }

    public function testSaveFileReturnsInsertId(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveFile($this->dbPath, 'File upload', 'photo.jpg', 1024, '/tmp/photo.jpg', $ctx);
        $this->assertSame(1, $id);
    }

    public function testSaveFileStoresCorrectData(): void
    {
        $ctx = $this->makeContext();
        DatabaseAction::saveFile($this->dbPath, 'Upload', 'doc.pdf', 2048, '/storage/doc.pdf', $ctx, '{"note":"test"}');

        $row = DatabaseAction::getById($this->dbPath, 1);
        $this->assertNotNull($row);
        $this->assertSame('file', $row['type']);
        $this->assertSame('Upload', $row['subject']);
        $this->assertSame('{"note":"test"}', $row['body']);
        $this->assertSame('doc.pdf', $row['filename']);
        $this->assertSame('/storage/doc.pdf', $row['file_path']);
        $this->assertEquals(2048, $row['file_size']);
    }

    public function testGetByIdReturnsNullForMissing(): void
    {
        // Force DB init by saving something first
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());
        $this->assertNull(DatabaseAction::getById($this->dbPath, 999));
    }

    public function testUpdateEntrySubject(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Old', 'Body', $ctx);

        $updated = DatabaseAction::updateEntry($this->dbPath, $id, 'New', null);
        $this->assertNotNull($updated);
        $this->assertSame('New', $updated['subject']);
        $this->assertSame('Body', $updated['body']);
    }

    public function testUpdateEntryBody(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Title', 'Old body', $ctx);

        $updated = DatabaseAction::updateEntry($this->dbPath, $id, null, 'New body');
        $this->assertNotNull($updated);
        $this->assertSame('Title', $updated['subject']);
        $this->assertSame('New body', $updated['body']);
    }

    public function testUpdateEntryReturnsNullForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());
        $this->assertNull(DatabaseAction::updateEntry($this->dbPath, 999, 'x', null));
    }

    // -- Delete operations ----------------------------------------------------

    public function testDeleteEntryRemovesRow(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Del', 'Me', $ctx);

        $deleted = DatabaseAction::deleteEntry($this->dbPath, $id);
        $this->assertNotNull($deleted);
        $this->assertSame('Del', $deleted['subject']);

        $this->assertNull(DatabaseAction::getById($this->dbPath, $id));
    }

    public function testDeleteEntryCascadesToAttachments(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        DatabaseAction::appendText($this->dbPath, $id, 'Att1', 'Body1', $ctx);
        DatabaseAction::appendText($this->dbPath, $id, 'Att2', 'Body2', $ctx);

        $deleted = DatabaseAction::deleteEntry($this->dbPath, $id);
        $this->assertCount(2, $deleted['attachments']);

        $atts = DatabaseAction::getAttachments($this->dbPath, $id);
        $this->assertEmpty($atts);
    }

    public function testDeleteEntryReturnsNullForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());
        $this->assertNull(DatabaseAction::deleteEntry($this->dbPath, 999));
    }

    public function testDeleteAttachment(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        $attId = DatabaseAction::appendText($this->dbPath, $id, 'Att', 'Body', $ctx);

        $deleted = DatabaseAction::deleteAttachment($this->dbPath, $attId);
        $this->assertNotNull($deleted);
        $this->assertSame('Att', $deleted['subject']);

        $this->assertNull(DatabaseAction::getAttachmentById($this->dbPath, $attId));
    }

    // -- Append mode ----------------------------------------------------------

    public function testAppendTextCreatesAttachment(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        $attId = DatabaseAction::appendText($this->dbPath, $entryId, 'Note', 'Extra info', $ctx);

        $this->assertSame(1, $attId);
        $atts = DatabaseAction::getAttachments($this->dbPath, $entryId);
        $this->assertCount(1, $atts);
        $this->assertSame('text', $atts[0]['type']);
        $this->assertSame('Note', $atts[0]['subject']);
        $this->assertSame('Extra info', $atts[0]['body']);
    }

    public function testAppendFileCreatesAttachment(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        $attId = DatabaseAction::appendFile(
            $this->dbPath, $entryId, 'Screenshot', 'shot.png', 5000, '/path/shot.png', null, $ctx
        );

        $this->assertSame(1, $attId);
        $att = DatabaseAction::getAttachmentById($this->dbPath, $attId);
        $this->assertNotNull($att);
        $this->assertSame('file', $att['type']);
        $this->assertSame('shot.png', $att['filename']);
    }

    public function testGetAttachmentsReturnsOrderedList(): void
    {
        $ctx = $this->makeContext('1.1.1.1', 'A', '2025-01-01T00:00:00+00:00');
        $entryId = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);

        $ctx2 = $this->makeContext('2.2.2.2', 'B', '2025-01-02T00:00:00+00:00');
        DatabaseAction::appendText($this->dbPath, $entryId, 'First', 'A', $ctx);
        DatabaseAction::appendText($this->dbPath, $entryId, 'Second', 'B', $ctx2);

        $atts = DatabaseAction::getAttachments($this->dbPath, $entryId);
        $this->assertCount(2, $atts);
        $this->assertSame('First', $atts[0]['subject']);
        $this->assertSame('Second', $atts[1]['subject']);
    }

    public function testGetByIdWithAttachmentsIncludesNestedData(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        DatabaseAction::appendText($this->dbPath, $entryId, 'Att', 'Note', $ctx);

        $result = DatabaseAction::getByIdWithAttachments($this->dbPath, $entryId, 'http://localhost/api');
        $this->assertNotNull($result);
        $this->assertSame($entryId, $result['id']);
        $this->assertArrayHasKey('attachments', $result);
        $this->assertCount(1, $result['attachments']);
        $this->assertSame('Att', $result['attachments'][0]['subject']);
    }

    // -- Pagination -----------------------------------------------------------

    public function testGetAllPaginatedReturnsPagedResults(): void
    {
        $ctx = $this->makeContext();
        for ($i = 1; $i <= 5; $i++) {
            DatabaseAction::saveText($this->dbPath, "Entry {$i}", "Body {$i}", $ctx);
        }

        $result = DatabaseAction::getAllPaginated($this->dbPath, 1, 2);
        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(2, $result['per_page']);
        $this->assertCount(2, $result['entries']);
    }

    public function testGetAllPaginatedPageTwo(): void
    {
        $ctx = $this->makeContext();
        for ($i = 1; $i <= 5; $i++) {
            DatabaseAction::saveText($this->dbPath, "Entry {$i}", "Body {$i}", $ctx);
        }

        $result = DatabaseAction::getAllPaginated($this->dbPath, 3, 2);
        $this->assertCount(1, $result['entries']);
    }

    public function testGetAllPaginatedIncludesAttachmentCount(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Has attachments', 'Body', $ctx);
        DatabaseAction::appendText($this->dbPath, $id, 'A1', 'B1', $ctx);
        DatabaseAction::appendText($this->dbPath, $id, 'A2', 'B2', $ctx);

        $result = DatabaseAction::getAllPaginated($this->dbPath, 1, 10);
        $this->assertSame(2, $result['entries'][0]['attachment_count']);
    }

    public function testGetAllPaginatedWithFieldFilter(): void
    {
        $ctx = $this->makeContext();
        $id1 = DatabaseAction::saveText($this->dbPath, 'Tagged', 'Body', $ctx);
        DatabaseAction::saveText($this->dbPath, 'Untagged', 'Body', $ctx);

        // Get an existing status option ID
        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];
        DatabaseAction::setEntryFieldValue($this->dbPath, $id1, 'status', $openId);

        $result = DatabaseAction::getAllPaginated($this->dbPath, 1, 10, ['status' => $openId]);
        $this->assertSame(1, $result['total']);
        $this->assertSame('Tagged', $result['entries'][0]['subject']);
    }

    // -- Custom fields CRUD ---------------------------------------------------

    public function testSeededDefaultFields(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $fields = DatabaseAction::getAllCustomFields($this->dbPath);
        $names = array_column($fields, 'name');
        $this->assertContains('project', $names);
        $this->assertContains('status', $names);
        $this->assertContains('resolution', $names);
    }

    public function testSeededStatusOptions(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $names = array_column($options, 'name');
        $this->assertContains('open', $names);
        $this->assertContains('in progress', $names);
        $this->assertContains('closed', $names);
    }

    public function testCreateCustomField(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createCustomField($this->dbPath, 'priority', 'Bug priority', 5);

        $field = DatabaseAction::getCustomFieldByName($this->dbPath, 'priority');
        $this->assertNotNull($field);
        $this->assertSame('priority', $field['name']);
        $this->assertSame('Bug priority', $field['description']);
        $this->assertSame(5, $field['sort_order']);
    }

    public function testUpdateCustomField(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createCustomField($this->dbPath, 'severity', 'Sev', 0);
        $updated = DatabaseAction::updateCustomField($this->dbPath, 'severity', 'Severity level', 10);

        $this->assertNotNull($updated);
        $this->assertSame('Severity level', $updated['description']);
        $this->assertSame(10, $updated['sort_order']);
    }

    public function testUpdateCustomFieldReturnsNullForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $this->assertNull(DatabaseAction::updateCustomField($this->dbPath, 'nonexistent', 'desc', 0));
    }

    public function testDeleteCustomField(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createCustomField($this->dbPath, 'temp', 'Temporary', 0);
        $this->assertTrue(DatabaseAction::deleteCustomField($this->dbPath, 'temp'));
        $this->assertNull(DatabaseAction::getCustomFieldByName($this->dbPath, 'temp'));
    }

    public function testDeleteCustomFieldReturnsFalseForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $this->assertFalse(DatabaseAction::deleteCustomField($this->dbPath, 'nonexistent'));
    }

    public function testDeleteCustomFieldCascadesToOptions(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createCustomField($this->dbPath, 'env', 'Environment', 0);
        DatabaseAction::createOption($this->dbPath, 'env', 'production');
        DatabaseAction::createOption($this->dbPath, 'env', 'staging');

        $this->assertTrue(DatabaseAction::deleteCustomField($this->dbPath, 'env'));
        $this->assertEmpty(DatabaseAction::getAllOptions($this->dbPath, 'env'));
    }

    public function testGetAllCustomFieldsIncludesOptionCount(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $fields = DatabaseAction::getAllCustomFields($this->dbPath);
        $statusField = null;
        foreach ($fields as $f) {
            if ($f['name'] === 'status') {
                $statusField = $f;
                break;
            }
        }
        $this->assertNotNull($statusField);
        $this->assertSame(3, $statusField['option_count']); // open, in progress, closed
    }

    // -- Field options CRUD ---------------------------------------------------

    public function testCreateOption(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $id = DatabaseAction::createOption($this->dbPath, 'project', 'Alpha');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $opt = DatabaseAction::getOptionById($this->dbPath, 'project', $id);
        $this->assertNotNull($opt);
        $this->assertSame('Alpha', $opt['name']);
    }

    public function testUpdateOption(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $id = DatabaseAction::createOption($this->dbPath, 'project', 'Beta');
        $updated = DatabaseAction::updateOption($this->dbPath, 'project', $id, 'Beta v2');
        $this->assertNotNull($updated);
        $this->assertSame('Beta v2', $updated['name']);
    }

    public function testUpdateOptionReturnsNullForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $this->assertNull(DatabaseAction::updateOption($this->dbPath, 'project', 9999, 'Ghost'));
    }

    public function testDeleteOption(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $id = DatabaseAction::createOption($this->dbPath, 'project', 'Gamma');
        $this->assertTrue(DatabaseAction::deleteOption($this->dbPath, 'project', $id));
        $this->assertNull(DatabaseAction::getOptionById($this->dbPath, 'project', $id));
    }

    public function testDeleteOptionReturnsFalseForMissing(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $this->assertFalse(DatabaseAction::deleteOption($this->dbPath, 'project', 9999));
    }

    public function testDuplicateOptionThrowsException(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createOption($this->dbPath, 'project', 'Unique');
        $this->expectException(\PDOException::class);
        DatabaseAction::createOption($this->dbPath, 'project', 'Unique');
    }

    // -- Pivot/field values ---------------------------------------------------

    public function testSetAndGetEntryFieldValue(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Tagged', 'Body', $ctx);

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];

        DatabaseAction::setEntryFieldValue($this->dbPath, $entryId, 'status', $openId);
        $values = DatabaseAction::getEntryFieldValues($this->dbPath, $entryId);

        $this->assertArrayHasKey('status', $values);
        $this->assertSame($openId, $values['status']);
    }

    public function testSetEntryFieldValueUpserts(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Tagged', 'Body', $ctx);

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];
        $inProgressId = $options[1]['id'];

        DatabaseAction::setEntryFieldValue($this->dbPath, $entryId, 'status', $openId);
        DatabaseAction::setEntryFieldValue($this->dbPath, $entryId, 'status', $inProgressId);

        $values = DatabaseAction::getEntryFieldValues($this->dbPath, $entryId);
        $this->assertSame($inProgressId, $values['status']);
    }

    public function testSetAndGetAttachmentFieldValue(): void
    {
        $ctx = $this->makeContext();
        $entryId = DatabaseAction::saveText($this->dbPath, 'Parent', 'Body', $ctx);
        $attId = DatabaseAction::appendText($this->dbPath, $entryId, 'Att', 'Note', $ctx);

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];

        DatabaseAction::setAttachmentFieldValue($this->dbPath, $attId, 'status', $openId);
        $values = DatabaseAction::getAttachmentFieldValues($this->dbPath, $attId);

        $this->assertArrayHasKey('status', $values);
        $this->assertSame($openId, $values['status']);
    }

    public function testUpdateEntryWithFieldValues(): void
    {
        $ctx = $this->makeContext();
        $id = DatabaseAction::saveText($this->dbPath, 'Entry', 'Body', $ctx);

        $options = DatabaseAction::getAllOptions($this->dbPath, 'status');
        $openId = $options[0]['id'];

        $updated = DatabaseAction::updateEntry($this->dbPath, $id, null, null, ['status' => $openId]);
        $this->assertNotNull($updated);

        $values = DatabaseAction::getEntryFieldValues($this->dbPath, $id);
        $this->assertSame($openId, $values['status']);
    }

    // -- Export / Import ------------------------------------------------------

    public function testExportCustomFields(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $export = DatabaseAction::exportCustomFields($this->dbPath);
        $this->assertArrayHasKey('fields', $export);
        $this->assertGreaterThanOrEqual(3, count($export['fields']));

        $statusField = null;
        foreach ($export['fields'] as $f) {
            if ($f['name'] === 'status') {
                $statusField = $f;
                break;
            }
        }
        $this->assertNotNull($statusField);
        $this->assertContains('open', $statusField['options']);
    }

    public function testImportCustomFieldsMergeMode(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        $result = DatabaseAction::importCustomFields($this->dbPath, [
            ['name' => 'platform', 'description' => 'Target platform', 'sort_order' => 5, 'options' => ['iOS', 'Android']],
        ]);

        $this->assertSame(1, $result['fields_created']);
        $this->assertSame(2, $result['options_created']);

        $field = DatabaseAction::getCustomFieldByName($this->dbPath, 'platform');
        $this->assertNotNull($field);
        $options = DatabaseAction::getAllOptions($this->dbPath, 'platform');
        $this->assertCount(2, $options);
    }

    public function testImportIgnoresDuplicates(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        // Import the already-seeded status field
        $result = DatabaseAction::importCustomFields($this->dbPath, [
            ['name' => 'status', 'description' => 'Status', 'options' => ['open']],
        ]);

        $this->assertSame(0, $result['fields_created']);
        $this->assertSame(0, $result['options_created']);
    }

    public function testExportImportRoundTrip(): void
    {
        // Force DB init
        DatabaseAction::saveText($this->dbPath, 'x', 'x', $this->makeContext());

        DatabaseAction::createCustomField($this->dbPath, 'roundtrip', 'Test field', 99);
        DatabaseAction::createOption($this->dbPath, 'roundtrip', 'opt_a');
        DatabaseAction::createOption($this->dbPath, 'roundtrip', 'opt_b');

        $export = DatabaseAction::exportCustomFields($this->dbPath);

        // Import into a fresh DB
        $dbPath2 = $this->createTempDbPath();
        // Force init of the second DB
        DatabaseAction::saveText($dbPath2, 'x', 'x', $this->makeContext());

        $result = DatabaseAction::importCustomFields($dbPath2, $export['fields']);

        // The 3 seeded fields already exist, so only roundtrip field + options are new
        $this->assertSame(1, $result['fields_created']);
        $this->assertSame(2, $result['options_created']);
    }
}
