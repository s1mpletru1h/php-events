<?php
namespace Services\Events;

class Events
{

    const VERBOSE  = 0;
    const DEBUG    = 1;
    const INFO     = 2;
    const WARNING  = 3;
    const ERROR    = 4;
    const CRITICAL = 5;
    const LOG_LEVELS   = ['VERBOSE', 'DEBUG', 'INFO', 'WARNING', 'ERROR',
        'CRITICAL'];
    const LEVEL_COLORS = ['gray', 'cyan', 'green', 'yellow', 'magenta', 'red'];
    const LOG_DIR    = './tmp/logs';
    const COLORS   = [
        'gray'    => '0;37',
        'cyan'    => '0;36',
        'magenta' => '0;35',
        'yellow'  => '0;33',
        'green'   => '0;32',
        'red'     => '0;31',
    ];

    /**
     * Initialize.
     *
     * @param  string  $thread
     * @param  int     $log_level
     * @param  bool    $log_to_stdout
     * @param  bool    $log_to_master
     * @param  bool    $use_color
     * @param  string  $log_dir
     * @param  bool    $show_exception_trace
     */
    public function __construct(string $thread,
        int $log_level = self::DEBUG,
        bool $log_to_stdout = true,
        bool $log_to_master = false,
        bool $use_color = true,
        string $log_dir = self::LOG_DIR,
        bool $show_exception_trace = false,
        int  $keep_events = 100,
        int  $max_hist = 200)
    {
        $this->_thread = $thread;
        $this->_log_level = $log_level;
        $this->_log_dir   = $log_dir;

        $this->_log_to_stdout = $log_to_stdout;
        $this->_log_to_master = $log_to_master;
        $this->_use_color = $use_color;

        $this->_show_exception_trace = $show_exception_trace;
        $this->_thread_log = $this->_log_dir.'/'.$thread.'.log';
        $this->_master_log = $this->_log_dir.'/master.log';
        $this->_keep_events = $keep_events;
        $this->_max_hist = $max_hist;

        $this->_createLogDir();
        $this->_createLogFile($this->_thread_log);
        $this->_createLogFile($this->_master_log);
        $this->_initSubscribers();
        $this->_initHistory();
    }

    /**
     * Publish an event and trigger all callbacks.
     * Return an array of collected callback return values.
     *
     * @param  string  $event
     * @param  array   $data
     * @return array
     */
    public function pub(string $event, array $data = []): array
    {
        $this->log($event, $data);
        $callbacks = $this->_getEventSubscribers($event);
        return $this->_fireCallbacks($callbacks, $event, $data);
    }

    /**
     * Subscribe (register the triggering of a function)
     * in response to an event.
     *
     * @param  string   $event
     * @param  callable $callback
     */
    public function sub(string $event, callable $callback)
    {
        $this->_pushSubscribers($event, $callback);
    }

    /**
     * Log an event to standard out and or files.
     * Todo: Implement database and in-memory logging.
     *
     * @param  string  $event
     * @param  array   $data
     */
    public function log(string $event, array $data = [])
    {
        $event_level = $this->_getEventLevel($event);
        if ($event_level >= $this->_log_level)
        {
            $event_timestamp = $this->_getNowTimestamp();
            $event_level_str = self::LOG_LEVELS[$event_level];
            $event_name      = $this->_getEventName($event);
            $event_thread    = $this->_thread;
            $event_message   = $event_timestamp.' - '
                .$event_level_str.' - '.$event_thread.' - '.$event_name;
            if (sizeof($data) > 0)
            {
                $event_message .= ' - '.json_encode($data)."\n";
            } else
            {
                $event_message .= "\n";
            }
            $this->_logToHistory($event_name, $data, $event_timestamp, $event_thread);
            if ($this->_log_to_stdout) {
                $this->_logToStdout($event_message, $event_level);
            }
            $this->_writeToFile($this->_thread_log, $event_message);
            if ($this->_log_to_master) {
                $this->_writeToFile($this->_master_log, $event_message);
            }
        }
    }

    /**
     * Unsubscribe all $event callback subscribers.
     *
     * @param  string  $event
     */
    public function unsub(string $event)
    {
        $this->_clearEventSubscribers($event);
    }

    /**
     * Unsubscribe all callback subscribers.
     *
     * @param  bool    $confirm
     */
    public function reset(bool $confirm = false)
    {
        if ($confirm === true)
        {
            $this->_clearAllSubscribers();
        }
    }

    /**
     * Return true if every event in $events did occur within the last $n events.
     * $n is limited by $this->_keep_events.
     *
     * @param  array    $events
     * @param  int      $n
     */
    public function didOccur(array $events, int $n = 100)
    {
        $recent_history = array_map(function ($details) use ($n) {
            return $details['event'];
        }, array_slice($GLOBALS['EVENT_HIST'], -$n));
        foreach ($events as $event) {
            if (!in_array($event, $recent_history))
            {
                return false;
            }
        }
        return true;
    }

    private function _logToHistory(string $event, array $data,
        string $timestamp, string $thread)
    {
        $this->_trimHistory();
        array_push($GLOBALS['EVENT_HIST'], [
            'event' => $event,
            'data' => $data,
            'timestamp' => $timestamp,
            'thread' => $thread
        ]);
    }

    private function _trimHistory()
    {
        $hist_size = sizeof($GLOBALS['EVENT_HIST']);
        $excess = $hist_size - $this->_keep_events;
        if ($hist_size > $this->_max_hist)
        {
            array_splice($GLOBALS['EVENT_HIST'], 0, $excess);
        }
    }

    private function _logToStdout(string $message, int $event_level)
    {
        if ($this->_use_color)
        {
            $color = self::LEVEL_COLORS[$event_level];
            $message = $this->_colorize($message, $color);
        }
        echo($message);
    }

    private function _colorize(string $line, string $color)
    {
        return "\033[" . self::COLORS[$color] . "m" . $line . "\033[0m";
    }

    private function _writeToFile(string $file, string $line)
    {
        return file_put_contents($file, $line, FILE_APPEND);
    }

    private function _getNowTimestamp()
    {
        return $this->_strftimeu("%Y-%m-%dT%H:%M:%S.%f%z");
    }

    private function _createLogDir()
    {
        if (!file_exists($this->_log_dir))
        {
            return mkdir($this->_log_dir, 0777, true);
        }
    }

    private function _createLogFile(string $path)
    {
        if (!file_exists($path))
        {
            return touch($path);
        }
    }

    private function _initSubscribers()
    {
        if(!isset($GLOBALS['SUBSCRIBERS']))
        {
            $GLOBALS['SUBSCRIBERS'] = [];
        }
    }

    private function _initHistory()
    {
        if(!isset($GLOBALS['EVENT_HIST']))
        {
            $GLOBALS['EVENT_HIST'] = [];
        }
    }

    private function _getEventSubscribers(string $event)
    {
        return isset($GLOBALS['SUBSCRIBERS'][strtoupper($event)]) ?
            $GLOBALS['SUBSCRIBERS'][strtoupper($event)] : [];
    }

    private function _pushSubscribers(string $event, callable $callback)
    {
        if (!isset($GLOBALS['SUBSCRIBERS'][strtoupper($event)]))
        {
            $GLOBALS['SUBSCRIBERS'][strtoupper($event)] = [];
        }
        array_push($GLOBALS['SUBSCRIBERS'][strtoupper($event)], $callback);
    }

    private function _clearEventSubscribers(string $event)
    {
        if (isset($GLOBALS['SUBSCRIBERS'][strtoupper($event)]))
        {
            $GLOBALS['SUBSCRIBERS'][strtoupper($event)] = [];
        }
    }

    private function _clearAllSubscribers()
    {
        $GLOBALS['SUBSCRIBERS'] = [];
    }

    private function _getEventLevel(string $event)
    {
        $level = self::DEBUG;
        if (strpos($event, ':'))
        {
            $level_str = explode(':', $event)[1];
            $level = array_search($level_str, self::LOG_LEVELS);
        }
        return $level;
    }

    private function _getEventName(string $event)
    {
        $event_name = $event;
        if (strpos($event, ':'))
        {
            $event_name = explode(':', $event)[0];
        }
        return $event_name;
    }

    private function _fireCallbacks(array $callbacks, string $event,
        array $data): array
    {
        $returns = [];
        foreach ($callbacks as $event => $callback) {
            try {
                array_push($returns, call_user_func_array($callback,
                    [$event, $data]));
            } catch (\Exception $e) {
                $exception = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine()
                ];
                if ($this->_show_exception_trace)
                {
                    array_push($exception, $e->getTrace());
                }
                $this->pub('EXCEPTION:ERROR', $exception);
            }
        }
        return $returns;
    }

    /**
    * @param string $format strftime format
    * @param float $microtime time with microsecond
    * @return string
    */
    private function _strftimeu(string $format, float $microtime = 0)
    {
        if ($microtime == 0)
        {
            $microtime = microtime(true);
        }
        if (preg_match('/^[0-9]*\\.([0-9]+)$/', $microtime, $reg)) {
            $decimal = substr(str_pad($reg[1], 6, "0"), 0, 6);
        } else {
            $decimal = "000000";
        }
        $format = preg_replace('/(%f)/', $decimal, $format);
        return strftime($format, $microtime);
    }
}