#!/usr/bin/env php
<?php

define('BASE_DIR', __DIR__);

require_once __DIR__ . '/phpCron.php';

$cron = new PhpCron();
$cron->debugMe('./debug.log');
$cron->withoutOverlapping();
$cron->stop();

$cron
    ->exec("echo 1")->cron('17,32,46,54 * * * * * *')->between('2018-06-10 10:00:00', '2018-06-30 10:00:00')
    ->exec("echo 2")->cron('*/10 * * * * * *')->when(function() {return 2 == 1 + 1;})
;

$cron->start(true);