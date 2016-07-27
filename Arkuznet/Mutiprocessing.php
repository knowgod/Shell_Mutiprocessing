<?php

/**
 * Interface Arkuznet_Multiprocessing_Interface
 */
interface Arkuznet_Multiprocessing_Interface
{
    const IS_DEBUG_MODE = true;

    const ARG_PARENT_ID = 'parent_id';
    const ARG_PAGE_START = 'page_start';
    const ARG_PAGE_FINISH = 'page_finish';
    const ARG_LOG_TO = 'log_to';

    /**
     * Return named command line argument
     *
     * @param string $name the argument name
     * @return string
     */
    public function getArg($name);

    /***
     * @param int $pages How many pages to split
     * @param int $processes Number of process to split pages among
     * @return bool
     */
    public function initMultiprocess($pages, $processes);

    /**
     * Perform sub-process actions
     *
     * @return mixed
     */
    public function runSubProcess();
}

/**
 * Trait Arkuznet_Multiprocessing
 * Class which uses this should also implement Arkuznet_Multiprocessing_Interface
 *
 * @see Arkuznet_Multiprocessing_Interface
 *
 * @category Arkuznet
 * @package Arkuznet_Multiprocessing
 * @author Arkadij Kuzhel <a.kuzhel@youwe.nl>
 */
trait Arkuznet_Multiprocessing
{
    /**
     * This the entry point
     * Call this method initializes the multiprocessing procedure
     *
     * @param int $startPage Start from page
     * @param int $endPage End at page
     * @param int $processes Number of process to split pages among
     * @param string $logFilePrefix
     * @return bool
     */
    public function initMultiprocess($startPage = 0, $endPage = 0, $processes = 4, $logFilePrefix = 'multiprocess')
    {
        $output = true;
        if ($this->isChild()) {
            try {
                $this->runSubProcess();
            } catch (Exception $e) {
                $this->log('Sub-process failed: ' . $e->getMessage());
                $output = false;
            }
        } else {
            try {
                $aProcesses = $this->_dispatch($startPage, $endPage, $processes, $logFilePrefix);
                $this->_logProcesses($aProcesses);
            } catch (Exception $e) {
                $this->log('Initialization failed: ' . $e->getMessage());
                $output = false;
            }
        }
        return $output;
    }

    /**
     * Determine if this is child process
     *
     * @return bool
     */
    public function isChild()
    {
        return ($this->getArg(static::ARG_PARENT_ID) ? true : false);
    }

    /**
     * @param int $startPage Start from page
     * @param int $endPage End at page
     * @param int $processes Number of processes to split pages among
     * @param string $logFilePath
     * @return array
     */
    protected function _dispatch($startPage, $endPage, $processes, $logFilePath = '')
    {
        $perProcess = ceil(($endPage - $startPage + 1) / $processes);
        $aPages = range($startPage, $endPage);
        $aBorders = array_chunk($aPages, $perProcess);

        $command = array();
        $argument = array(static::ARG_PARENT_ID => getmypid());

        foreach ($aBorders as $i => $aChunk) {
            $logTo = $logFilePath . $i . '.log';
            $argument[static::ARG_PAGE_START] = array_shift($aChunk);
            $argument[static::ARG_PAGE_FINISH] = count($aChunk) ? array_pop($aChunk) : $argument[static::ARG_PAGE_START];
            $argument[static::ARG_LOG_TO] = $logTo;

            $parameters = array_map(array($this, '_prepareArgument'), array_keys($argument), $argument);


            $command[$i] = 'php ' . $_SERVER['SCRIPT_FILENAME'] . ' ' . implode(' ', $parameters) . ' >' . $logTo;
        }

        $handle = array();
        foreach ($command as $run) {
            $this->log($run);

            if (!static::IS_DEBUG_MODE) {
                $handle[] = popen($run, "r");
            }
        }

        return $handle;
    }

    /**
     * Log subprocesses output
     *
     * @param array $handles
     */
    protected function _logProcesses(array $handles)
    {
        while (count($handles)) {
            foreach ($handles as $i => $rPointer) {
                if (is_resource($rPointer)) {
                    if (!feof($rPointer)) {
                        $this->log($i . ': ' . fgets($rPointer));
                    } else {
                        pclose($rPointer);
                        unset($handles[$i]);
                    }
                }
            }
        }
    }

    /**
     * Prepare argument for command line
     *
     * @param $argName
     * @param $argValue
     * @return string
     */
    protected function _prepareArgument($argName, $argValue)
    {
        return "-$argName $argValue";
    }

    /**
     * The simplest log
     * You may want to override this
     *
     * @param mixed $msg
     */
    public function log($msg)
    {
        echo $msg, PHP_EOL;
    }
}
