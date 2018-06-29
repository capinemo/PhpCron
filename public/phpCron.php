<?php

/**
 * phpCron - PHP Cron analogue
 *
 * Tasks execution scheduler (console or php scripts) at a certain time
 * 
 * 1. Requiments:
 *  - php7.0
 *  - php7-cli
 *  - php7-pcntl
 *  - php7-posix
 *  - php7-sysvmsg
 * 
 * 2. Installation
 * 
 * 3. Usage
 * 
 * 4. Documentation & testing
 * 
 * 
 * @package     phpCron
 * @author      Sadykov Rustem <capitan__nemo@mail.ru>
 * @version     0.5.0
 * @copyright   Copyright (c) 2018, Picum.ru
 *
 */
class phpCron
{
    /**
     * Count of running processes
     * @var integer 
     */
    private $pcount = 0;

    /**
     * File name of the parent process pid
     * @var string
     */
    private $pid_file_name = null;

    /**
     * File name of the list with child processes pids
     * @var string
     */
    private $pids_list_name = null;

    /**
     * Restart lock file name
     * If exists start of the same process is blocked
     * @var string 
     */
    private $restart_block_name = null;

    /**
     * Debug output file name
     * @var string 
     */
    private $debug_log_name = null;

    /**
     * Date and time of process start
     * @var string
     */
    private $start_cron = null;

    /**
     * Date and time of process finish
     * @var string
     */
    private $stop_cron = null;

    /**
     * ID of the current task
     * Used in schedule generating process. When calls 'exec' or 'call' methods
     * this parameter changes.
     * @var string 
     */
    private $actual_id = null;

    /**
     * Pid of current process (parent or schedule childs)
     * @var integer 
     */
    private $pid = null;

    /**
     * Resource pointer to parent pid file
     * @var resource 
     */
    private $pfile = null;

    /**
     * List of tasks statuses
     * @var array 
     */
    private $tasks = [];

    /**
     * List of scheduled tasks
     * @var array 
     */
    private $schedule = [];

    /**
     * List of tasks prepared for execution in next iteration if tasks runs
     * independence
     * @var array 
     */
    private $plan = [];

    /**
     * List of tasks prepared for execution in next iteration if tasks
     * runs over withoutOverlappingAll method in queue
     * @var array 
     */
    private $queue = [];

    /**
     * List of child processes pids
     * TODO add desciption
     * @var type 
     */
    private $process = [];

    /**
     * Errors log
     * @var array 
     */
    private $errors = [];

    /**
     * TODO add desciption (Флаг дочернего процесса на запуск обработки задачи)
     * @var boolean 
     */
    private $child_run_flag = false;

    /**
     * TODO add desciption (Флаг того что очередь занята выполнением задачи)
     * @var boolean 
     */
    private $queue_busy_flag = false;

    /**
     * phpCron options
     * @var array 
     */
    private $options = [
        // Queue type depending on needed to run all tasks in turn 
        // or only the same type (all|task)
        'queue' => null,
        // Maximum size of queue
        'queue_limit' => 200,
        // Maximum count of running child processes
        'max_pcount' => 300,
        // Default timezone
        'timezone' => 'Europe/Moscow',
        // Debug mode flag
        'debug' => false,
        // If task already in the queue, next execution ignore
        'no_double' => false,
        // Die time of child process, if it pid does not contain in the child pids list
        'child_die_time'=> 60 * 60,
        // Execution in testing mode (testing before deploy)
        'isTest'        => false,
        // Default language for messages
        'defLang'       => 'en'
    ];
    
    /**
     * phpCron array of the messages with selected language
     * @var array 
     */
    private $message = [];
    
    /**
     * phpCron messages
     * @var array 
     */
    private $mess = [
        'en' => [
            'cron_unparse'          => 'Not valid format of string in phpCron::cron(), given: ',
            'hourlyAt_unparse'      => 'Not valid format of string in phpCron::hourlyAt(), given: ',
            'hourlyAt_empty'        => 'Parameter must have MM:SS format in phpCron::hourlyAt(), given: ',
            'dailyAt_unparse'       => 'Not valid format of string in phpCron::dailyAt(), given: ',
            'dailyAt_empty'         => 'Parameter must have HH:MM:SS format in phpCron::dailyAt(), given: ',
            'monthlyOn_unparse'     => 'Not valid format of string in phpCron::monthlyOn(), given: ',
            'monthlyOn_empty'       => 'Parameter must have HH:MM:SS format in phpCron::monthlyOn(), given: ',
            ''        => '',
            ''        => '',
            ''        => '',
            ''        => '',
            ''        => '',
            ''        => '',
            ''        => '',
        ],
        'ru' => [],
    ];

    /**
     * Sets the names of the service files
     * 
     * @return phpCron
     * @final
     */
    final public function __construct()
    {
        $this->pid_file_name = "/tmp/phpcron_" . get_current_user() . ".pid";
        $this->pids_list_name = "/tmp/phpcron_" . get_current_user() . ".pids";
        $this->restart_block_name = "/tmp/phpcron_" . get_current_user() . ".blk";
        $this->message = $this->mess[$this->options['defLang']];
    }

    /**********         COMMANDS            **********/
    /**
     * Get shell command for execution
     *
     * @param string $string Shell script for execution
     * @return self
     * @final
     */
    final public function exec($string)
    {
        $this->createTask('exec', (string) $string);
        
        return $this;
    }

    /**
     * Get php script for execution
     *
     * @param function $callback Php function for execution
     * @return self
     * @final
     */
    final public function call($callback)
    {
        $this->createTask('call', $callback);
        
        return $this;
    }

    /**********         PLANNING            **********/
    /**
     * Set execution schedule in order to given string
     * 
     * @param string $string
     * @return self
     * @final
     */
    final public function cron($string = '* * * * * * *')
    {
        $time = explode(' ', $string);
        $matches = [];
        $setMajorTimeLevel = false;
        $parsing_error = false;

        for ($i = count($time) - 1; $i >= 0; $i--) {
            if ($time[$i] == '*') {
                // Every moment

                $this->schedule[$this->actual_id][$i] = 1;
                
            }  else if ((preg_match('/^\*\/([0-9]+)$/', $time[$i], $matches))) {
                // Every moment with period
                
                $this->schedule[$this->actual_id][$i] = $matches[1];
                
            } else if (preg_match('/^([0-9]{1,4})$/', $time[$i], $matches)) {
                // Certain moment
                
                $this->schedule[$this->actual_id][$i] = [$matches[0]];
                
            } else if ((preg_match('/^([0-9,\-]+)$/', $time[$i], $matches))) {
                // Several certain moments

                $this->schedule[$this->actual_id][$i] = [];

                foreach (explode(',', $matches[0]) as $value) {
                    
                    if ((preg_match('/^([0-9]{1,4})$/', $value, $matches))) {
                        // Moment in list
                        
                        if (!in_array($value, $this->schedule[$this->actual_id][$i])) {
                            array_push($this->schedule[$this->actual_id][$i], $value);
                        }
                        
                    } else if (preg_match('/^([0-9\-]+)$/', $value, $matches)) {
                        // Period in list
                        
                        $period = explode('-', $matches[0]);
                        
                        for ($y = $period[0]; $y <= $period[1]; $y++) {

                            if (!in_array($y, $this->schedule[$this->actual_id][$i])) {
                                array_push($this->schedule[$this->actual_id][$i], $y);
                            }
                        }
                    } else {
                        $parsing_error = true;
                    }
                }
            } else {
                $parsing_error = true;
            }
            
            // If gets invalid time pointer generate exeption 
            // and writes debug log
            if ($parsing_error) {
                $this->generateError($this->message['cron_unparse'] . $string , true);
            }
            
            
            // $setMajorTimeLevel - variable indicates that high levels (year, month, 
            // days, etc.) of time are set. In this case every moment notation (*)
            // interprets as minimal value of time interval (0 for second, minutes and 
            // hours; 1 for days, month and years)
            if ($time[$i] != '*') {
                if ($i != count($time) - 1) $setMajorTimeLevel = true;
            } else {
                if ($setMajorTimeLevel) {
                    $this->schedule[$this->actual_id][$i] = $i > 2 ? [1] : [0];
                }
            }
        }

        return $this;
    }

    /**
     * Set execution schedule at every second
     * 
     * @return self
     * @final
     */
    final public function everySeconds()
    {
        $this->schedule[$this->actual_id][0] = 1;

        return $this;
    }

    /**
     * Set execution schedule at every five second 
     * 
     * @return self
     * @final
     */
    final public function everyFiveSeconds()
    {
        $this->schedule[$this->actual_id][0] = 5;

        return $this;
    }

    /**
     * Set execution schedule at every ten second 
     * 
     * @return self
     * @final
     */
    final public function everyTenSeconds()
    {
        $this->schedule[$this->actual_id][0] = 10;

        return $this;
    }

    /**
     * Set execution schedule at every thirty second 
     * 
     * @return self
     * @final
     */
    final public function everyThirtySeconds()
    {
        $this->schedule[$this->actual_id][0] = 30;

        return $this;
    }

    /**
     * Set execution schedule at every minute in 0 second
     * 
     * @return self
     * @final
     */
    final public function everyMinute()
    {
        $this->schedule[$this->actual_id][0] = [0];

        return $this;
    }

    /**
     * Set execution schedule at every five minute in 0 second
     * 
     * @return self
     * @final
     */
    final public function everyFiveMinutes()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 5;

        return $this;
    }

    /**
     * Set execution schedule at every ten minute in 0 second
     * 
     * @return self
     * @final
     */
    final public function everyTenMinutes()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 10;

        return $this;
    }

    /**
     * Set execution schedule at every thirty minute in 0 second
     * 
     * @return self
     * @final
     */
    final public function everyThirtyMinutes()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 30;

        return $this;
    }

    /**
     * Set execution schedule at every minute and certain second 
     * 
     * @param integer $sc Set certain second for execution in format SS
     * @return self
     * @final
     */
    final public function minutelyAt($sc)
    {
        $this->schedule[$this->actual_id][0] = [(int) $sc];

        return $this;
    }

    /**
     * Set execution schedule at every hour in 0 minute and 0 second
     * 
     * @return self
     * @final
     */
    final public function hourly()
    {
            $this->schedule[$this->actual_id][0] = [0];
            $this->schedule[$this->actual_id][1] = [0];

        return $this;
    }

    /**
     * Set execution schedule at every hour and certain minute and second 
     * 
     * @param string $mn_sc Set certain second and minute for execution 
     * in format MM:SS
     * @return self
     * @final
     */
    final public function hourlyAt($mn_sc)
    {
        $time = array_reverse(explode(':', $mn_sc));
        
        if (count($time) != 2) {
            $this->generateError($this->message['hourlyAt_unparse'] . (string) $mn_sc , true);
        } else if (empty($time[0]) || empty($time[1]) ) {
            $this->generateError($this->message['hourlyAt_empty'] . (string) $mn_sc , true);
        }

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];

        return $this;
    }

    /**
     * Set execution schedule at every day in 00:00:00 
     * 
     * @return self
     * @final
     */
    final public function daily()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];

        return $this;
    }

    /**
     * Set execution schedule at every hour and certain hour, minute and second 
     * 
     * @param string $hr_mn_sc Set certain second, minute and hour for execution 
     * in format HH:MM:SS
     * @return self
     * @final
     */
    final public function dailyAt($hr_mn_sc)
    {
        $time = array_reverse(explode(':', $hr_mn_sc));
        
        if (count($time) != 3) {
            $this->generateError($this->message['dailyAt_unparse'] . (string) $hr_mn_sc , true);
        } else if (empty($time[0]) || empty($time[1])  || empty($time[2]) ) {
            $this->generateError($this->message['dailyAt_empty'] . (string) $hr_mn_sc , true);
        }

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];
        $this->schedule[$this->actual_id][2] = [(int) $time[2]];

        return $this;
    }

    /**
     * Set execution schedule twice a day in certain hours, 0 minute and 0 second 
     * 
     * @param string $first_hr Set certain hour for execution at day in format HH
     * @param string $second_hr Set certain hour for execution at day in format HH
     * @return self
     * @final
     */
    final public function twiceDaily($first_hr, $second_hr)
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [(int) $first_hr, (int) $second_hr];

        return $this;
    }

    /**
     * Set execution schedule at every first day of month in 00:00:00 
     * 
     * @return self
     * @final
     */
    final public function monthly()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];

        return $this;
    }

    /**
     * Set execution schedule at every cenrtain day of month in 00:00:00 
     * 
     * @param string $day_num et certain second day for execution at month 
     * in format DD
     * @param string $hr_mn_sc Set certain second, minute and hour for execution 
     * in format HH:MM:SS
     * @return self
     * @final
     */
    final public function monthlyOn($day_num, $hr_mn_sc)
    {
        $time = array_reverse(explode(':', $hr_mn_sc));
        
        if (count($time) != 3) {
            $this->generateError($this->message['monthlyOn_unparse'] . (string) $hr_mn_sc , true);
        } else if (empty($time[0]) || empty($time[1])  || empty($time[2]) ) {
            $this->generateError($this->message['monthlyOn_empty'] . (string) $hr_mn_sc , true);
        }

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];
        $this->schedule[$this->actual_id][2] = [(int) $time[2]];
        $this->schedule[$this->actual_id][3] = [(int) $day_num];

        return $this;
    }

    /**
     * Set execution schedule at first day of every second month in 00:00:00 
     * 
     * @return self
     * @final
     */
    final public function everyTwoMonth()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = 2;

        return $this;
    }

    /**
     * Set execution schedule at first day of every third month in 00:00:00 
     * 
     * @return self
     * @final
     */
    final public function quarterly()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = [1, 4, 7, 10];

        return $this;
    }

    /**
     * Set execution schedule at every year in first day of january in 00:00:00 
     * 
     * @return self
     * @final
     */
    final public function yearly()
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = [1];

        return $this;
    }

    /**********         RESTRICTIONS            **********/
    /**
     * Set execution schedule at every working day
     * 
     * @return self
     * @final
     */
    final public function weekdays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(1, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 1);
        }

        if (!in_array(2, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 2);
        }

        if (!in_array(3, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 3);
        }

        if (!in_array(4, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 4);
        }

        if (!in_array(5, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 5);
        }

        return $this;
    }

    /**
     * Set execution schedule at every sunday
     * 
     * @return self
     * @final
     */
    final public function sundays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(0, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 0);
        }

        return $this;
    }

    /**
     * Set execution schedule at every monday
     * 
     * @return self
     * @final
     */
    final public function mondays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(1, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 1);
        }

        return $this;
    }

    /**
     * Set execution schedule at every tuesday
     * 
     * @return self
     * @final
     */
    final public function tuesdays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(2, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 2);
        }

        return $this;
    }

    /**
     * Set execution schedule at every wednesday
     * 
     * @return self
     * @final
     */
    final public function wednesdays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(3, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 3);
        }

        return $this;
    }

    /**
     * Set execution schedule at every thursday
     * 
     * @return self
     * @final
     */
    final public function thursdays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(4, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 4);
        }

        return $this;
    }

    /**
     * Set execution schedule at every friday
     * 
     * @return self
     * @final
     */
    final public function fridays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(5, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 5);
        }

        return $this;
    }

    /**
     * Set execution schedule at every saturday
     * 
     * @return self
     * @final
     */
    final public function saturdays()
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(6, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 6);
        }

        return $this;
    }

    /**
     * Schedule a task in given interval
     * 
     * @param string $start_time Begin of interval
     * @param string $end_time End of interval
     * @return $this
     * @final
     */
    final public function between($start_time, $end_time)
    {
        $this->tasks[$this->actual_id]['limit'] = [
            date('U', strtotime($start_time))
            , date('U', strtotime($end_time))
        ];
        
         $this->tasks[$this->actual_id]['ltype'] = 'in';
        
        return $this;
    }

    /**
     * Schedule a task outside the specified interval
     * 
     * @param string $start_time Begin of interval
     * @param string $end_time End of interval
     * @return $this
     * @final
     */
    final public function unlessBetween ($start_time, $end_time)
    {
        $this->tasks[$this->actual_id]['limit'] = [
            date('U', strtotime($start_time))
            , date('U', strtotime($end_time))
        ];
        
         $this->tasks[$this->actual_id]['ltype'] = 'out';
        
        return $this;
    }

    /**
     * Schedule a task if callback return true
     * 
     * @param callable $callback Callback functuin for check
     * @return $this
     * @final
     */
    final public function when($callback)
    {
        $this->tasks[$this->actual_id]['truecheck'] = $callback;

        return $this;
    }

    /**
     * Schedule a task if callback return false
     * 
     * @param callable $callback Callback functuin for check
     * @return $this
     * @final
     */
    final public function skip($callback)
    {
        $this->tasks[$this->actual_id]['falsecheck'] = $callback;
                
        return $this;
    }

    /**********         OPTIONS            **********/
    /**
     * Runs each task without overlapping, different tasks may overlap
     *
     * @return $this
     * @final
     */
    final public function withoutOverlapping()
    {
        $this->options['queue'] = 'task';
        return $this;
    }

    /**
     * Runs all task without overlapping
     *
     * @param boolean $queue_no_double If next task must to run in task execution time it's skip
     * @return $this
     * @final
     */
    final public function withoutOverlappingAll($queue_no_double = false)
    {
        $this->options['queue'] = 'all';
        $this->options['no_double'] = $queue_no_double;

        return $this;
    }

    /**
     * Sets timezone for schedule
     * @param string $timezone
     * @return $this
     */
    public function timezone(string $timezone)
    {
        // Пока тоже откладывается. date(U), как и DateTime временную зону не учитывает,
        // нужно переделывать ключи, для учета временной зоны
        $this->options['timezone'] = $timezone;
        return $this;
    }

    /**
     * 
     * 
     * @param string $filename
     * @return \self
     */
    public function debugMe($filename = null)
    {
        $this->options['debug'] = true;

        if ($filename) {
            $this->debug_log_name = $filename;
            touch($this->debug_log_name);
        }

        return $this;
    }

                        /**********         RESULT            **********/
                        public function sendOutputTo(string $file): self    //NEED
                        {
                            return $this;
                        }

                        public function appendOutputTo(string $file): self  //NEED
                        {
                            return $this;
                        }

                        public function exportPhpCron(string $file): self   //NEED
                        {
                            return $this;
                        }

                        public function importPhpCron(string $file): self   //NEED
                        {
                            return $this;
                        }

                        public function saveToCronTab(string $user, string $crontab_path = null): self  //NEED
                        {
                            return $this;
                        }

                        public function loadFromCronTab(string $user, string $crontab_path = null): self    //NEED
                        {
                            return $this;
                        }

                        /**********         HOOKS            **********/
                        public function before(callable $callback): self    //NEED
                        {
                            return $this;
                        }

                        public function after(callable $callback): self //NEED
                        {
                            return $this;
                        }

                        /**********         PROCESS            **********/

                        /**
                         * Start PhpCron via creating children process with the prohibition
                         * of duplication
                         * Checks the pid file exists and process running.
                         * Deletes the pid file if the process not run.
                         * Exit if PhpCron process running.
                         * Parent process saves children pid before exit
                         * Children process makes self a session leader and installs a signal handler
                         *
                         * @return self
                         */
                        public function start($show_schedule = false): self
                        {
                            if ($show_schedule) {
                                echo print_r($this->tasks, true);
                                echo print_r($this->schedule, true);
                                exit();
                            }

                            if (file_exists($this->pid_file_name)) {
                                $pid = file_get_contents($this->pid_file_name);

                                if (posix_kill((int) $pid, 0)) {
                                    exit();

                                } else {
                                    if(!unlink($this->pid_file_name)) {
                                        die();
                                    }
                                }
                            }

                            declare(ticks = 1);

                            $pid = pcntl_fork();

                            if ($pid == -1) {
                                 die();
                            } else if ($pid) {
                                $this->pid = $pid;
                                $this->pfile = fopen($this->pid_file_name, "wb");
                                fwrite ($this->pfile, $this->pid);
                                exit();
                            }
                            // Родительский поток не успевает создать файл
                            usleep(100000);

                            $this->pfile = fopen($this->pid_file_name, "rb");
                            $this->pid = fread($this->pfile, filesize($this->pid_file_name));
                            flock ($this->pfile, LOCK_EX);

                            $actual_pid = posix_setsid();
                            if ($actual_pid == -1 && $actual_pid != $this->pid) {
                                die();
                            }

                            pcntl_signal(SIGHUP, array(&$this, "sigHandler"));
                            pcntl_signal(SIGTERM, array(&$this, "sigHandler"));
                            pcntl_signal(SIGCHLD, SIG_IGN); // избавляемся от зомби процессов

                            $this->listener();

                            return $this;
                        }

                        /**
                         * Stop old PhpCron process and restart it once
                         *
                         * @return self
                         */
                        public function restartOnce(): self
                        {
                            if (!file_exists($this->restart_block_name)) {
                                $this->stop();
                                touch($this->restart_block_name);
                                $this->start();
                            }

                            return $this;
                        }

                        /**
                         * Stop old PhpCron process and restart it
                         *
                         * @return self
                         */
                        public function restart(): self
                        {
                            $this
                                ->stop()
                                ->start();
                            return $this;
                        }

                        /**
                         * Stop PhpCron and delete pid file
                         *
                         * @return self
                         */
                        public function stop(): self
                        {
                            if (file_exists($this->pid_file_name)) {
                                // убиваем прошлые дочерние процессы
                                if (file_exists($this->pids_list_name)) {
                                    $last_pids = file($this->pids_list_name);
                                    foreach ($last_pids as $chpid) {
                                        posix_kill((int) $chpid, SIGTERM);
                                    }
                                    unlink ($this->pids_list_name);
                                }

                                // удаляем файл блокировки рестарта
                                if (file_exists($this->restart_block_name)){
                                    unlink ($this->restart_block_name);
                                }

                                // удаляем PID файл родительского процесса
                                $this->pfile = fopen($this->pid_file_name, "rb");
                                $this->pid = fread($this->pfile, filesize($this->pid_file_name));
                                flock ($this->pfile, LOCK_UN);
                                fclose ($this->pfile);
                                unlink ($this->pid_file_name);
                                posix_kill((int) $this->pid, SIGTERM);
                            }

                            return $this;
                        }

                        /**
                         * Reset all schedule tasks and user options without stopping PhpCron
                         * Stop PhpCron and delete pid file
                         *
                         * @return self
                         */
                        public function reset(): self
                        {
                            $this->actual_id = null;
                            $this->tasks = [];
                            $this->schedule = [];
                            $this->errors = [];

                            return $this;
                        }

                        /**********         OTHER            **********/

                        public function setOption(string $parameter, $value): self
                        {
                            $this->options[$parameter] = $value;

                            return $this;
                        }

                        public function getOption(string $parameter)
                        {
                            return $this->options[$parameter];
                        }

                        public function getShedule()
                        {
                            if ($this->options['isTest']) {
                                return $this->schedule;
                            }

                            return false;
                        }

                        /**
                         * Generates unique id for tasks
                         * @return string
                         */
                        private function genTaskId(): string
                        {
                            return uniqid('', true);
                        }

                        /**
                         * Insert new task into collection
                         *
                         * @param string $type commands nethod name
                         * @param type $content command content
                         * @return bool
                         */
                        private function createTask(string $type, $content): bool
                        {
                            $this->actual_id = $this->genTaskId();

                            $this->tasks[$this->actual_id] = [
                                'action' => $type,
                                'command' => $content,
                                // Хранит пид дочки, используется для обращения к дочернему процессу
                                // с целью перезапуска задачи согласно расписания
                                'pid' => null,
                                // бит, ставиться в true при генерации задачи, после первого запуска
                                // устанавливается в false и больше в генерации расписания не учавствует
                                // применяется при разовых задачах, у которых первые шесть символов
                                // в расписании числа, и ни одного массива
                                'live' => true,     //NEED
                                // Статус задачи, 1 - выполняется, 0 ожидание
                                'state' => false,
                                // уникальный ключ очереди
                                //'key' => $this->getUniqKey(10000, 99999),
                                // границы действия расписания
                                'limit' => [],
                                // тип границ (внешний, внутренний)
                                'ltype' => null,
                                // функция, возвращающая положительный результат запускает задачу
                                'truecheck' => null,
                                // функция, возвращающая отрицательный результат запускает задачу
                                'falsecheck' => null,
                            ];

                            $this->schedule[$this->actual_id] = [1, 1, 1, 1, 1, 1, 1];

                            return true;
                        }

                        /*private function getUniqKey($min, $max)
                        {
                            $key = uniqid($min, $max);

                            foreach ($this->tasks as $value) {
                                if ($value['key'] == $value) {
                                    $key = $this->uniqid($min, $max);
                                    break;
                                }
                            }
                            return $key;
                        }*/

                        private function listener()
                        {
                            $key = 0;
                            $run = false;
                            $stamp = date('U');
                            $timestamp = date('U') + $this->options['child_die_time'];
                            $this->start_cron = $stamp;
                            $start = microtime(true);
                            $queue = msg_get_queue(posix_getpid(), 0666);

                            $this->prepareNextTasks();

                            while (true) {
                                if (date('U') > $timestamp) {
                                    // проверяем что наш PID есть списке запущенных процессов, если нет - умираем
                                    if (file_exists($this->pid_file_name)) {
                                        $last_pids = file_get_contents($this->pid_file_name);

                                        if ((int)$last_pids != posix_getpid()) {
                                            posix_kill((int) posix_getpid(), SIGTERM);
                                        }
                                    } else {
                                        posix_kill((int) posix_getpid(), SIGTERM);
                                    }

                                    $timestamp = date('U') + $this->options['child_die_time'];
                                }


                                if ($stamp != date('U')) {
                                    $stamp = date('U');

                                    if ($this->options['debug']) {
                                        echo PHP_EOL;
                                        echo round(microtime(true) - $start, 4) . "\t";
                                        $start = microtime(true);
                                        echo date('Y-m-d_H:i:s') . "\t";
                                        echo $this->pcount . "\t";
                                        echo count($this->queue) . "\t";

                                    }

                                    $this->prepareNextTasks();

                                    if ($this->options['queue'] == 'all') {
                                        if (!$this->queue_busy_flag) {
                                            $this->executeQueueTasks();
                                        }
                                    } else {
                                        if (isset($this->plan[$stamp])) {
                                            if (!$this->options['queue'] && $this->pcount < $this->options['max_pcount']) {
                                                $this->executeTasks($this->plan[$stamp]);
                                            } else if ($this->options['queue'] == 'task') {
                                                $this->executeTasks($this->plan[$stamp]);
                                            }
                                            unset($this->plan[$stamp]);
                                        }
                                    }
                                }

                                if ($this->options['debug']) {
                                    echo '.';
                                }

                                // Слушаем очередь сообщений на наличие сигналов от дочерних
                                // процессов
                                if (msg_receive($queue, 0, $key, 8, $run, true, MSG_IPC_NOWAIT)) {
                                    if ($this->options['queue'] == 'all') {
                                        $this->tasks[$this->process[$key]]['state'] = $run;
                                        if ($run == false) {
                                            array_shift($this->queue);
                                            $this->queue_busy_flag = false;
                                        }
                                    } else if ($this->options['queue'] == 'task') {
                                        $this->tasks[$this->process[$key]]['state'] = $run;
                                    } else {
                                        $this->pcount--;
                                    }
                                }

                                usleep(50000);
                            }
                        }

                        private function prepareNextTasks()
                        {
                            $next_tick = (new DateTime(null, new DateTimeZone($this->options['timezone'])))->add(new DateInterval('PT1S'));

                            foreach($this->schedule as $key => $task) {
                                $this->calcNextTaskStamp($key, $next_tick);
                            }
                        }

                        private function calcNextTaskStamp($task_id, $next_sec)
                        {
                            $next_date = '';

                            $next_iter = preg_split('/ /', $next_sec->format('s i G j n y w'));
                            $next_iter[0] = (int) $next_iter[0];
                            $next_iter[1] = (int) $next_iter[1];

                            foreach ($this->schedule[$task_id] as $key => $value) {
                                if (is_numeric($value)) {
                                    if ($next_iter[$key] % $value) return false;
                                } else {
                                    if (!in_array($next_iter[$key], $value)) return false;
                                }
                            }

                            $ns = $next_sec->format('U');

                            if (!isset($this->plan[$ns]) && $this->options['queue'] != 'all') {
                                $this->plan[$ns] = [];
                            }

                            if ($this->options['queue'] != 'all') {
                                // Задачи запускаются независимо друг от друга
                                array_push($this->plan[$ns], $task_id);
                            } else {
                                // Все задачи должны запускаться по очереди
                                if (count($this->queue) < $this->options['queue_limit']) {
                                    // Если лимит очереди пока не превышен - добавляем
                                    if ($this->options['no_double']) {
                                        // Если стоит опция без повторов и раньше такая задача в очередь не добавлялась - добавляем
                                        if (!in_array($task_id, $this->queue)) {
                                            array_push($this->queue, $task_id);
                                        }
                                    } else {
                                        // Если опция без повторов не указана - добавляем
                                        array_push($this->queue, $task_id);
                                    }
                                }
                            }

                            return $next_date;
                        }

                        private function executeQueueTasks ()
                        {
                             if (count($this->queue) <= 0) {
                                 return;
                             }

                             if (!isset($this->queue[0])) {
                                 return;
                             }

                            $this->queue_busy_flag = false;

                            if ($this->tasks[$this->queue[0]]['pid']) {
                                if ($this->options['queue'] == 'all') {
                                    // Так все запуски не перекрываются
                                    if (!$this->tasks[$this->queue[0]]['state']) {
                                        posix_kill($this->tasks[$this->queue[0]]['pid'], SIGHUP);
                                    }
                                }
                            } else {
                                $pid = pcntl_fork();

                                if ($this->options['queue'] == 'all') {
                                    // Все задачи не должны перекрываться
                                    if ($pid == -1) {
                                        //ничего не делаем
                                    } else if ($pid) {
                                        $this->tasks[$this->queue[0]]['pid'] = $pid;
                                        $this->process[$pid] = $this->queue[0];
                                        $pids = fopen($this->pids_list_name, "ab");
                                        fwrite ($pids, $pid . PHP_EOL);
                                        $this->pcount++;
                                    } else {
                                        $this->runCommonTaskProcess($this->queue[0]);
                                    }
                                } else {
                                    // На всякий случай предусматриваем возможность разового запуска задачи
                                    if ($pid == 0) {
                                        $this->runSinleTaskProcess($this->queue[0]);
                                    } else if ($pid != -1) {
                                        $this->pcount++;
                                    }
                                }
                            }
                        }

                        private function executeTasks($task_list)
                        {
                            foreach ($task_list as $task) {
                                if ($this->tasks[$task]['pid']) {
                                    if ($this->options['queue'] == 'task') {
                                        // Так запуски задачи не перекрываются
                                        if (!$this->tasks[$task]['state']) {
                                            posix_kill($this->tasks[$task]['pid'], SIGHUP);
                                        }
                                    }
                                } else {
                                    $pid = pcntl_fork();

                                    if ($this->options['queue'] == 'task') {
                                        // Однотипные задачи не должны перекрываться
                                        if ($pid == -1) {
                                            //ничего не делаем
                                        } else if ($pid) {
                                            $this->tasks[$task]['pid'] = $pid;
                                            $this->process[$pid] = $task;
                                            $pids = fopen($this->pids_list_name, "ab");
                                            fwrite ($pids, $pid . PHP_EOL);
                                            $this->pcount++;
                                        } else {
                                            $this->runCommonTaskProcess($task);
                                        }
                                    } else {
                                        // Все задачи могут перекрываться
                                        if ($pid == 0) {
                                            $this->runSinleTaskProcess($task);
                                        } else if ($pid != -1) {
                                            $this->pcount++;
                                        }
                                    }
                                }
                            }
                        }


                        // Процесс выполняющий разовую задачу
                        private function runSinleTaskProcess($task)
                        {
                            $prpid = file_get_contents($this->pid_file_name);
                            $queue = msg_get_queue($prpid, 0666);

                            $task_param = $this->tasks[$task];
                            $this->runTask($task_param['action'], $task_param['command']);

                            msg_send($queue, posix_getpid(), (int) $this->child_run_flag);

                            posix_kill((int) posix_getgid(), SIGTERM);
                            exit();
                        }

                        // Процесс выполняющий одну и туже задачу с определенной периодичностью
                        private function runCommonTaskProcess($task)
                        {
                            $prpid = file_get_contents($this->pid_file_name);
                            $this->child_run_flag = true;
                            $queue = msg_get_queue($prpid, 0666);
                            $timestamp = date('U') + $this->options['child_die_time'];

                            while (true) {
                                if (date('U') > $timestamp) {
                                    // проверяем что наш PID есть списке запущенных процессов, если нет - умираем
                                    if (file_exists($this->pids_list_name)) {
                                        $last_pids = file($this->pids_list_name);
                                        $in_file = false;

                                        foreach ($last_pids as $chpid) {
                                            if ((int)$chpid == posix_getpid()) {
                                                $in_file = true;
                                                break;
                                            }
                                        }
                                        if (!$in_file) posix_kill((int) $chpid, SIGTERM);
                                    } else {
                                        if (!$in_file) posix_kill((int) $chpid, SIGTERM);
                                    }

                                    $timestamp = date('U') + $this->options['child_die_time'];
                                }

                                msg_send($queue, posix_getpid(), (int) $this->child_run_flag);

                                $task_param = $this->tasks[$task];
                                $this->runTask($task_param['action'], $task_param['command']);

                                $this->child_run_flag = false;
                                msg_send($queue, posix_getpid(), (int) $this->child_run_flag);

                                while (true) {
                                    if ($this->child_run_flag) {
                                        break;
                                    }
                                    usleep(50000);
                                }
                            }

                            exit();
                        }

                        private function runTask($type, $task)
                        {
                            if ($type == 'exec') {
                                system($task);
                            } else if ($type == 'call') {
                                $task();
                            }

                            return 1;
                        }

                        private function sigHandler($signo)
                        {
                            switch ($signo) {
                                case SIGTERM:
                                    // обработка сигнала завершения
                                    exit;
                                    break;
                                case SIGHUP:
                                    // обработка перезапуска задач
                                    $this->child_run_flag = true;
                                    break;
                                case SIGINT:
                                    break;
                                default:
                                    // обработка других сигналов
                            }
                        }
                        
    
    private function generateError($error_message, $gen_exeption) {
        $message = date('Y-m-d H:i:s') . "\t" . 'ERROR' . "\t" . $error_message . PHP_EOL;
        
        if ($this->options['debug'] && $this->debug_log_name) {
            file_put_contents($this->debug_log_name, $message, FILE_APPEND);
        } else {
            array_push($this->errors, $message);
        }
        
        if ($gen_exeption) {
            throw new \Exception($message);
        }
    }
}

if (isset($argv[1]) && $argv[1] == 'install') {
    $user = get_current_user();
    $script = "cron_{$user}.php";
    
    $content = '#!/usr/bin/env php' . PHP_EOL
        . '<?php' . PHP_EOL . PHP_EOL
        . 'define(\'BASE_DIR\', __DIR__);' . PHP_EOL . PHP_EOL
        . 'require_once __DIR__ . \'/phpCron.php\';' . PHP_EOL . PHP_EOL
        . '$cron = new PhpCron();' . PHP_EOL
        . '$cron->debugMe();' . PHP_EOL
        . '$cron->withoutOverlapping();' . PHP_EOL . PHP_EOL
        . '$cron' . PHP_EOL
        . '    '. '->exec(\'echo 1\')->cron(\'*/5 * * * * * *\')' . PHP_EOL
        . '    ' . '->restartOnce();' . PHP_EOL;

    
    file_put_contents($script, $content);
    
}