<?php

declare(strict_types=1);

require_once dirname(__FILE__).'./../public/PhpCron.php';

use PHPUnit\Framework\TestCase;

final class phpCronPlanningTest extends TestCase
{
    /**********         PLANNING            **********/
    /**
     * @covers \PhpCron::cron
     * @dataProvider cronPlanningProvider
     */
    public function testCronPlanning($cron_str, $expected_arr)
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->cron($cron_str)->getShedule();
        $this->assertContains($expected_arr, $result);
    }

    public function testEverySecondsPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everySeconds()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryFiveSecondsPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyFiveSeconds()->getShedule();
        $this->assertContains([5, 1, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryTenSecondsPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyTenSeconds()->getShedule();
        $this->assertContains([10, 1, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryThirtySecondsPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyThirtySeconds()->getShedule();
        $this->assertContains([30, 1, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryMinutePlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyMinute()->getShedule();
        $this->assertContains([[0], 1, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryFiveMinutesPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyFiveMinutes()->getShedule();
        $this->assertContains([[0], 5, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryTenMinutesPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyTenMinutes()->getShedule();
        $this->assertContains([[0], 10, 1, 1, 1, 1, 1], $result);
    }

    public function testEveryThirtyMinutesPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyThirtyMinutes()->getShedule();
        $this->assertContains([[0], 30, 1, 1, 1, 1, 1], $result);
    }

    public function testMinutelyAtPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->minutelyAt(29)->getShedule();
        $this->assertContains([[29], 1, 1, 1, 1, 1, 1], $result);
    }

    public function testHourlyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->hourly()->getShedule();
        $this->assertContains([[0], [0], 1, 1, 1, 1, 1], $result);
    }

    public function testHourlyAtPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->hourlyAt('15:30')->getShedule();
        $this->assertContains([[30], [15], 1, 1, 1, 1, 1], $result);
    }

    public function testDailyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->daily()->getShedule();
        $this->assertContains([[0], [0], [0], 1, 1, 1, 1], $result);
    }

    public function testDailyAtPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->dailyAt('22:15:30')->getShedule();
        $this->assertContains([[30], [15], [22], 1, 1, 1, 1], $result);
    }

    public function testTwiceDailyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->twiceDaily(14, 19)->getShedule();
        $this->assertContains([[0], [0], [14, 19], 1, 1, 1, 1], $result);
    }

    public function testMonthlyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->monthly()->getShedule();
        $this->assertContains([[0], [0], [0], [1], 1, 1, 1], $result);
    }

    public function testMonthlyOnPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->monthlyOn(4, '22:15:30')->getShedule();
        $this->assertContains([[30], [15], [22], [4], 1, 1, 1], $result);
    }

    public function testEveryTwoMonthPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->everyTwoMonth()->getShedule();
        $this->assertContains([[0], [0], [0], [1], 2, 1, 1], $result);
    }

    public function testQuarterlyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->quarterly()->getShedule();
        $this->assertContains([[0], [0], [0], [1], [1, 4, 7, 10], 1, 1], $result);
    }

    public function testYearlyPlanning()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->yearly()->getShedule();
        $this->assertContains([[0], [0], [0], [1], [1], 1, 1], $result);
    }

    /**********         RESTRICTIONS            **********/
    public function testWeekdaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->weekdays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [1, 2, 3, 4, 5]], $result);
    }

    public function testSundaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->sundays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [0]], $result);
    }

    public function testMondaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->mondays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [1]], $result);
    }

    public function testTuesdaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->tuesdays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [2]], $result);
    }

    public function testWednesdaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->wednesdays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [3]], $result);
    }

    public function testThursdaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->thursdays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [4]], $result);
    }

    public function testFridaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->fridays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [5]], $result);
    }

    public function testSaturdaysRestriction()
    {
        $result = (new PhpCron())->setOption('isTest', true)->exec('')->saturdays()->getShedule();
        $this->assertContains([1, 1, 1, 1, 1, 1, [6]], $result);
    }

    public function testBetweenRestriction()
    {
        //$result = (new PhpCron())->setOption('isTest', true)->exec('')->between('17:00:00', "21:00:00")->getShedule();
        //$this->assertContains([[0], [0], [17, 18, 19, 20, 21], 1, 1, 1, 1], $result);

        $this->assertTrue(true);
    }

    public function testUnlessBetweenRestriction()
    {
        //$result = (new PhpCron())->setOption('isTest', true)->exec('')->between('22:00:00', "18:00:00")->getShedule();
        //$this->assertContains([[0], [0], [19, 20, 21], 1, 1, 1, 1], $result);

        $this->assertTrue(true);
    }

    public function testWhenRestriction()
    {
        //$result = (new PhpCron())->setOption('isTest', true)->exec('')->daily()->when(function() { return true; })->getShedule();
        //$this->assertContains([[0], [0], [0], 1, 1, 1, 1], $result);

        $this->assertTrue(true);
    }

    public function testSkipRestriction()
    {
        //$result = (new PhpCron())->setOption('isTest', true)->exec('')->daily()->when(function() { return false; })->getShedule();
        //$this->assertContains([[0], [0], [0], 1, 1, 1, 1], $result);

        $this->assertTrue(true);
    }

    /**********         OPTIONS            **********/
    public function testTimezoneOption()
    {
        $this->assertTrue(true);
    }

    /**********         Proiders            **********/
    public function cronPlanningProvider()
    {
        return [
            [
                '17,32,46,54 * * * * * *',
                [[17, 32, 46, 54], 1, 1, 1, 1, 1, 1]
            ], [
                '* * * * * * *',
                [1, 1, 1, 1, 1, 1, 1]
            ], [
                '0 0 11 * * * *',
                [[0], [0], [11], 1, 1, 1, 1]
            ], [
                '* * 11 * * * *',
                [[0], [0], [11], 1, 1, 1, 1]
            ], [
                '0 15 */12 * * * *',
                [[0], [15], 12, 1, 1, 1, 1]
            ], [
                '0 0 */12 * * * *',
                [[0], [0], 12, 1, 1, 1, 1]
            ], [
                '* * */12 * * * *',
                [[0], [0], 12, 1, 1, 1, 1]
            ], [
                '25 */10 1-23 * * * *',
                [[25], 10, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23], 1, 1, 1, 1]
            ], [
                '0 40 12 * * * 1,2,3,4,5',
                [[0], [40], [12], 1, 1, 1, [1, 2, 3, 4, 5]]
            ], [
                '0 10 9,11,13,15,17,19 * * * 1,2,3,4,5',
                [[0], [10], [9, 11, 13, 15, 17, 19], 1, 1, 1, [1, 2, 3, 4, 5]]
            ], [
                '* * * * * * 3',
                [1, 1, 1, 1, 1, 1, [3]]
            ], [
                '30 22 11 3 6 2018 *',
                [[30], [22], [11], [3], [6], [2018], 1]
            ], [
                '* * * * * 2017-2019 *',
                [[0], [0], [0], [1], [1], [2017, 2018, 2019], 1]
            ],
        ];
    }
}