<?php

declare(strict_types=1);

require_once dirname(__FILE__).'./../public/phpCron.php';

use PHPUnit\Framework\TestCase;

final class phpCronProcessTest extends TestCase
{
    /**********         PROCESS            **********/
    public function testStartProcess()
    {
        $this->assertTrue(true);
    }

    public function testRestartOnceProcess()
    {
        $this->assertTrue(true);
    }

    public function testRestartProcess()
    {
        $this->assertTrue(true);
    }

    public function testStopProcess()
    {
        $this->assertTrue(true);
    }

    public function testResetProcess()
    {
        $this->assertTrue(true);
    }

    /**********         HOOKS            **********/
    public function testBeforeHook()
    {
        $this->assertTrue(true);
    }

    public function testAfterHook()
    {
        $this->assertTrue(true);
    }
}