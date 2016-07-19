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
     * @param int $pages How many pages to split
     * @param int $processes Number of process to split pages among
     * @param string $logFilePrefix
     * @return bool
     */
    public function initMultiprocess($pages = 100, $processes = 4, $logFilePrefix = '')
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
                $this->_dispatch($pages, $processes, $logFilePrefix);
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
     * @param int $pages How many pages to split
     * @param int $processes Number of process to split pages among
     * @param string $logFilePath
     * @return array
     */
    protected function _dispatch($pages, $processes, $logFilePath)
    {
        $perProcess = ceil($pages / $processes);

        $command = $argument = array();
        for ($i = 0; $i < $processes; ++$i) {
            $argument[static::ARG_PAGE_START] = $i * $perProcess;
            $argument[static::ARG_PAGE_FINISH] = ($i + 1) * $perProcess - 1;
            $argument[static::ARG_PARENT_ID] = getmypid();

            $parameters = array_map(array($this, '_prepareArgument'), array_keys($argument), $argument);

            $logTo = $logFilePath . $i . '.log';

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
