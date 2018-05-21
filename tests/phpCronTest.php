<?php

declare(strict_types=1);

require_once dirname(__FILE__).'./../public/PhpCron.php';

use PHPUnit\Framework\TestCase;

final class phpCronTest extends TestCase
{
    public function testInstanceOfPhpCron()
    {
        $this->assertInstanceOf(
            phpCron::class,
            new phpCron()
        );
    }

    public function testPhpCron()
    {
        $this->assertTrue(true);
    }
}