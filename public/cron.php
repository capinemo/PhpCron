#!/usr/bin/env php
<?php

define('BASE_DIR', __DIR__);

require_once __DIR__ . 'PhpCron.php';

$cron = new PhpCron();
$cron->debugMe('/debug.log');
$cron->withoutOverlapping();
$cron->stop();

$cron
    ->exec("echo 1")->cron('17,32,46,54 * * * * * *')
    ->exec("echo 2")->cron('*/10 * * * * * *')
;

$cron->start(true);