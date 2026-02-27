<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\RequestContext;
use PHPUnit\Framework\TestCase;

class RequestContextTest extends TestCase
{
    public function testConstructorReadsServerVars(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['SERVER_NAME'] = 'test.example.com';

        $ctx = new RequestContext();

        $this->assertSame('192.168.1.100', $ctx->ip);
        $this->assertSame('PHPUnit/Test', $ctx->ua);
        $this->assertSame('test.example.com', $ctx->fromDomain);
        $this->assertNotEmpty($ctx->time);
    }

    public function testDefaultsWhenServerVarsMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['SERVER_NAME']);

        $ctx = new RequestContext();

        $this->assertSame('unknown', $ctx->ip);
        $this->assertSame('unknown', $ctx->ua);
        $this->assertSame('localhost', $ctx->fromDomain);
    }
}
