<?php

declare(strict_types=1);

require_once dirname(__FILE__).'./../public/phpCron.php';

use PHPUnit\Framework\TestCase;

final class phpCronCommandsTest extends TestCase
{
    /**********         COMMANDS            **********/
    public function testExecCommand()
    {
        $this->assertTrue(true);
    }

    public function testCallCommand()
    {
        $this->assertTrue(true);
    }
}