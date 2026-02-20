<?php
/**
 * Simple DTO holding per-request metadata.
 */
class RequestContext
{
    /** @var string Client IP address */
    public $ip;

    /** @var string User-Agent header */
    public $ua;

    /** @var string ISO 8601 timestamp */
    public $time;

    /** @var string Server domain used in From: headers */
    public $fromDomain;

    public function __construct()
    {
        $this->ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->ua         = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->time       = date('c');
        $this->fromDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
    }
}
