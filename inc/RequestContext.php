<?php
declare(strict_types=1);

namespace App;

/**
 * Simple DTO holding per-request metadata.
 */
class RequestContext
{
    public string $ip;
    public string $ua;
    public string $time;
    public string $fromDomain;

    public function __construct()
    {
        $this->ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->ua         = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->time       = date('c');
        $this->fromDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
    }
}
