<?php
/**
 * Webhook Endpoint — Configuration & Bootstrap
 *
 * Receives text or file payloads from the Flutter app and dispatches
 * them to email and/or disk storage based on the config below.
 *
 * Edit the $config array, then upload this folder to your server.
 */

$config = [
  // ── Email ───────────────────────────────────────────────
  'email_enabled'  => true,
  'email_to'       => 'b.kobjolke@xida.de',

  // ── File storage on server ──────────────────────────────
  'storage_enabled' => false,
  'storage_path'    => __DIR__ . '/uploads',

  // ── Basic Auth (validate incoming requests) ─────────────
  'auth_enabled'  => false,
  'auth_username' => 'user',
  'auth_password' => 'pass',

  // ── Size limits ─────────────────────────────────────────
  'max_file_size' => 10 * 1024 * 1024,   // 10 MB
  'max_text_size' =>  1 * 1024 * 1024,   //  1 MB

  // ── Response sent back to the app ───────────────────────
  'response_message' => 'OK',
];

require __DIR__ . '/inc/WebhookHandler.php';
(new WebhookHandler($config))->run();
