<?php
/**
 * Main orchestrator — validates the request, dispatches to the
 * appropriate handler, and returns the configured response.
 */
class WebhookHandler
{
    /** @var array Global config from share.php */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->requireFiles();
    }

    /**
     * Entry point — called from share.php.
     */
    public function run(): void
    {
        // ── POST only ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // ── Request context ───────────────────────────────
        $ctx = new RequestContext();

        // ── Basic Auth ────────────────────────────────────
        if ($this->config['auth_enabled']) {
            if (!AuthValidator::validate(
                $this->config['auth_username'],
                $this->config['auth_password']
            )) {
                http_response_code(401);
                header('WWW-Authenticate: Basic realm="Webhook"');
                exit('Unauthorized');
            }
        }

        // ── Dispatch ──────────────────────────────────────
        if (!empty($_FILES['file'])) {
            FileHandler::handle($this->config, $ctx);
        } else {
            TextHandler::handle($this->config, $ctx);
        }

        // ── Success response ──────────────────────────────
        http_response_code(200);
        echo $this->config['response_message'] . "\n";
    }

    /**
     * Auto-require all class files from the inc/ directory.
     */
    private function requireFiles(): void
    {
        $base = __DIR__;

        require_once $base . '/RequestContext.php';
        require_once $base . '/AuthValidator.php';
        require_once $base . '/actions/EmailAction.php';
        require_once $base . '/actions/StorageAction.php';
        require_once $base . '/formatters/LogarteFormatter.php';
        require_once $base . '/formatters/MarkdownFormatter.php';
        require_once $base . '/handlers/FileHandler.php';
        require_once $base . '/handlers/TextHandler.php';
    }
}
