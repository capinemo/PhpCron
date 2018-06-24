#!/usr/bin/env php
<?php

define('BASE_DIR', __DIR__);

require_once __DIR__ . '/phpCron.php';

$cron = new PhpCron();
$cron->debugMe();
$cron->withoutOverlapping();

$cron
    ->exec('echo 1')->cron('*/5 * * * * * *')
    ->restartOnce();
