#!/usr/bin/env php
<?php

require_once 'library/PhpCron/PhpCron.php';

$cron = new PhpCron();

//Настрйки
//$cron->withoutOverlapping()->timezone('Europe/Lisbon')->debugMe();
//$cron->withoutOverlappingAll()->timezone('Europe/Lisbon')->debugMe();
//$cron->timezone('Europe/Lisbon')->debugMe();
$cron->debugMe();
//$cron->withoutOverlapping();
$cron->withoutOverlappingAll();

$cron->stop();

/*$cron->call(function() {
    $str = "COUNT: " . date('U') . PHP_EOL; // DEBUG:NEMO
    file_put_contents('log.txt', $str); // DEBUG:NEMO*/
//})->cron('1 5,5,5-6 * */4 3,5,8-10 * *');
/*})->cron('35-40 * * * * * *');

$cron->call(function() {
})->cron('35-40 1 * * * * *');

$cron->call(function() {
})->cron('* 1 * * * * *');

$cron->call(function() {
})->cron('0-59 * * * * * *');*/



/*$cron->call(function() {
    usleep(100000);
    echo '1';
})->cron('0-59 * * * * * *');

$cron->call(function() {
    echo '2';
})->cron('0 30 * * * * *');

$cron->call(function() {*/
    //echo '3';
//})->cron('* */2 * * * * *');

/*$cron->call(function() {
   echo '4';
    //file_put_contents('log.txt', "TICK:\t" . microtime(true) . "\t" . date('Y-m-d H:i:s') . "\t\t\t\t" . 4 . "\t" . PHP_EOL, FILE_APPEND); // DEBUG:NEMO
})->cron('0-59 * * * * * *');
*/


$cron->call(function() {
    echo '>>';
    usleep(2800000);
    echo '<<';
    //file_put_contents('log.txt', "TICK:\t" . microtime(true) . "\t" . date('Y-m-d H:i:s') . "\t\t\t" . 3 . "\t" . PHP_EOL, FILE_APPEND); // DEBUG:NEMO
})->cron('* * * * * * *');

$cron->call(function() {
    echo '+>';
    usleep(1400000);
    echo '<+';
    //file_put_contents('log.txt', "TICK:\t" . microtime(true) . "\t" . date('Y-m-d H:i:s') . "\t\t\t" . 3 . "\t" . PHP_EOL, FILE_APPEND); // DEBUG:NEMO
})->cron('* * * * * * *');










//$cron->call(function() {
//})->cron('15 20 1 3 */2 * 0,4,1');

//$cron->call(function() {
//})->sundays()->mondays()->thursdays()->quarterly()->monthlyOn(3, '1:20:15');

//$cron->start();





define('BASE_DIR', __DIR__);

require_once __DIR__ . '/library/PhpCron/PhpCron.php';

$cron = new PhpCron();

//$cron->debugMe();
$cron->withoutOverlapping();
$cron->stop()->hourly();

$cron->exec("php " . __DIR__ . "/run os loadme Users;")->cron('0 30 */2 * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme Clients;")->cron('30 */2 * * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme SummaryByClients;")->cron('0 */12 * * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme ClientObjects;")->cron('0 0 */2 * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme Locations;")->cron('30 */2 * * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme Applications;")->cron('* */2 * * * * *');
$cron->exec("php " . __DIR__ . "/run os loadme ClientsTimeSheetsLite;")->cron('* */20 * * * * *');

$cron->start();

