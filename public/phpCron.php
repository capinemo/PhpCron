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
     * Resource pointer to debug log file 
     * @var type 
     */
    private $debug_stream = null;
    
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
        'isTest'        => false
    ];

    /**
     * Set
     */
    public function __construct()
    {
        $this->pid_file_name = "/tmp/phpcron_" . get_current_user() . ".pid";
        $this->pids_list_name = "/tmp/phpcron_" . get_current_user() . ".pids";
        $this->restart_block_name = "/tmp/phpcron_" . get_current_user() . ".blk";
        $this->debug_log_name = __DIR__ . "/phpcron_" . get_current_user() . ".log";
    }

    /**********         COMMANDS            **********/
    /**
     * Set shell command for execution
     *
     * @param string $string
     * @return self
     *
     */
    public function exec(string $string): self
    {
        // TODO clean it
        $this->createTask('exec', $string);
        return $this;
    }

    public function call(callable $callback): self
    {
        $this->createTask('call', $callback);
        return $this;
    }

    /**********         PLANNING            **********/
    /**
     * Schedule prepare string
     * @param string $string
     * @return self
     */
    public function cron($string = '* * * * * * *'): self
    {
        $time = explode(' ', $string);
        $matches = [];
        $set_time = false;

        for ($i = count($time) - 1; $i >= 0; $i--) {
            if ($time[$i] == '*') {
            // Каждый момент единицы времени
                if ($set_time) {
                    $this->schedule[$this->actual_id][$i] = $i > 2 ? [1] : [0];
                    continue;
                }
                $this->schedule[$this->actual_id][$i] = 1;
            }  else if ((preg_match('/^\*\/([0-9]+)$/', $time[$i], $matches))) {
            // Момент единицы времени с определенной приодичностью
                /*if ($set_time) {
                    $this->schedule[$this->actual_id][$i] = $i > 2 ? [1] : [0];
                    continue;
                }*/
                $this->schedule[$this->actual_id][$i] = $matches[1];
                if ($i != count($time) - 1) $set_time = true;
            } else if (preg_match('/^([0-9]{1,2})$/', $time[$i], $matches)) {
            // Конкретный момент времени
                $this->schedule[$this->actual_id][$i] = [$matches[0]];
                if ($i != count($time) - 1) $set_time = true;
            } else if ((preg_match('/^([0-9,\-]+)$/', $time[$i], $matches))) {
            // Несколько конкретных моментов времени
                $this->schedule[$this->actual_id][$i] = [];

                foreach (explode(',', $matches[0]) as $value) {
                    if ((preg_match('/^([0-9]{1,4})$/', $value, $matches))) {
                        if (in_array($value, $this->schedule[$this->actual_id][$i])) continue;
                        array_push($this->schedule[$this->actual_id][$i], $value);
                        If ($i != count($time) - 1) $set_time = true;
                    } else if (preg_match('/^([0-9\-]+)$/', $value, $matches)) {
                        $period = explode('-', $matches[0]);
                        for ($y = $period[0]; $y <= $period[1]; $y++) {
                            if (in_array($y, $this->schedule[$this->actual_id][$i])) continue;
                            array_push($this->schedule[$this->actual_id][$i], $y);
                            If ($i != count($time) - 1) $set_time = true;
                        }
                    }
                }
            }
        }

        return $this;
    }

    public function everySeconds(): self
    {
        $this->schedule[$this->actual_id][0] = 1;

        return $this;
    }

    public function everyFiveSeconds(): self
    {
        $this->schedule[$this->actual_id][0] = 5;

        return $this;
    }

    public function everyTenSeconds(): self
    {
        $this->schedule[$this->actual_id][0] = 10;

        return $this;
    }

    public function everyThirtySeconds(): self
    {
        $this->schedule[$this->actual_id][0] = 30;

        return $this;
    }

    public function everyMinute(): self
    {
        $this->schedule[$this->actual_id][0] = [0];

        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 5;

        return $this;
    }

    public function everyTenMinutes(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 10;

        return $this;
    }

    public function everyThirtyMinutes(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = 30;

        return $this;
    }

    public function minutelyAt(int $sc): self
    {
        $this->schedule[$this->actual_id][0] = [(int) $sc];

        return $this;
    }

    public function hourly(): self
    {
            $this->schedule[$this->actual_id][0] = [0];
            $this->schedule[$this->actual_id][1] = [0];

        return $this;
    }

    public function hourlyAt(string $mn_sc): self
    {
        $time = array_reverse(explode(':', $mn_sc));

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];

        return $this;
    }

    public function daily(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];

        return $this;
    }

    public function dailyAt(string $hr_mn_sc): self
    {
        $time = array_reverse(explode(':', $hr_mn_sc));

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];
        $this->schedule[$this->actual_id][2] = [(int) $time[2]];

        return $this;
    }

    public function twiceDaily(int $first_hr, int $second_hr): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [(int) $first_hr, (int) $second_hr];

        return $this;
    }

    public function monthly(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];

        return $this;
    }

    public function monthlyOn(int $day_num, string $hr_mn_sc): self
    {
        $time = array_reverse(explode(':', $hr_mn_sc));

        $this->schedule[$this->actual_id][0] = [(int) $time[0]];
        $this->schedule[$this->actual_id][1] = [(int) $time[1]];
        $this->schedule[$this->actual_id][2] = [(int) $time[2]];
        $this->schedule[$this->actual_id][3] = [(int) $day_num];

        return $this;
    }

    public function everyTwoMonth(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = 2;

        return $this;
    }

    public function quarterly(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = [1, 4, 7, 10];

        return $this;
    }

    public function yearly(): self
    {
        $this->schedule[$this->actual_id][0] = [0];
        $this->schedule[$this->actual_id][1] = [0];
        $this->schedule[$this->actual_id][2] = [0];
        $this->schedule[$this->actual_id][3] = [1];
        $this->schedule[$this->actual_id][4] = [1];

        return $this;
    }

    /**********         RESTRICTIONS            **********/
    public function weekdays(): self
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

    public function sundays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(0, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 0);
        }

        return $this;
    }

    public function mondays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(1, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 1);
        }

        return $this;
    }

    public function tuesdays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(2, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 2);
        }

        return $this;
    }

    public function wednesdays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(3, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 3);
        }

        return $this;
    }

    public function thursdays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(4, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 4);
        }

        return $this;
    }

    public function fridays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(5, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 5);
        }

        return $this;
    }

    public function saturdays(): self
    {
        if (gettype($this->schedule[$this->actual_id][6]) != 'array' ) {
            $this->schedule[$this->actual_id][6] = [];
        }

        if (!in_array(6, $this->schedule[$this->actual_id][6])) {
            array_push($this->schedule[$this->actual_id][6], 6);
        }

        return $this;
    }

    public function between(string $start_time, string $end_time): self   //NEED
    {
        return $this;
    }

    public function unlessBetween (string $start_time, string $end_time): self   //NEED
    {
        return $this;
    }

    public function when(callable $callback): self   //NEED
    {
        return $this;
    }

    public function skip(callable $callback): self   //NEED
    {
        return $this;
    }

    /**********         OPTIONS            **********/
    public function withoutOverlapping(): self
    {
        $this->options['queue'] = 'task';
        return $this;
    }

    public function withoutOverlappingAll($queue_no_double = false): self
    {
        $this->options['queue'] = 'all';
        $this->options['no_double'] = $queue_no_double;

        return $this;
    }

    public function timezone(string $timezone): self    //NEED
    {
        // Пока тоже откладывается. date(U), как и DateTime временную зону не учитывает,
        // нужно переделывать ключи, для учета временной зоны
        $this->options['timezone'] = $timezone;
        return $this;
    }

    /**
     * 
     * @param string $filename
     * @return \self
     * @final
     */
    public function debugMe(string $filename = null): self
    {
        $this->options['debug'] = true;
        
        if ($filename) {
            $this->debug_log_name = $filename;
        }

        $this->debug_stream = fopen($this->debug_log_name, 'wb');
        
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
            'live' => null,     //NEED
            // Статус задачи, 1 - выполняется, 0 ожидание
            'state' => false,
            // уникальный ключ очереди
            //'key' => $this->getUniqKey(10000, 99999),
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
}