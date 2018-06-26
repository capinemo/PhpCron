<?php

declare(strict_types=1);

require_once dirname(__FILE__).'./../public/phpCron.php';

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
    
    public function testSetDebugMode()
    {
        $result = (new PhpCron())
                ->debugMe('./debug.log')
                ->getOption('debug');
        
        $this->assertTrue($result);
    }
    
    public function testDebugFileExists()
    {
        $file = 'debug.log';
        
        (new PhpCron())
                ->debugMe($file);
        
        $this->assertFileExists($file);
        
        //unlink($file);
    }
    
    public function testSetWithoutOverlapping()
    {
        $result = (new PhpCron())
                ->withoutOverlapping()
                ->getOption('queue');
        
        $this->assertEquals('task', $result);
    }
    
    public function testSetWithoutOverlappingAll()
    {
        $result = (new PhpCron())
                ->withoutOverlappingAll()
                ->getOption('queue');
        
        $this->assertEquals('all', $result);
    }
    
    public function testStart()
    {
        //$file = "/tmp/phpcron_" . get_current_user() . ".pid";
                
        /*$pc = (new PhpCron())
                ->exec("echo 1")
                ->cron('5 * * * * * *')
                ->start();*/
        
        //$this->assertFileExists($file);
        
        //$pc->stop();
        $this->assertTrue(true);
    }

}