<?php

class PhammLog
{
    private $day;
    private $hour;
    private $ip;
    private $resultLabel;
    private $logFile;
    private $log_row;

    public function __construct()
    {
        $this->day = date('Y'.'-'.'m'.'-'.'d');
        $this->hour = date ('H'.':'.'i'.':'.'s');
        $this->ip = $_SERVER["REMOTE_ADDR"];
    }

    /**
    * Write a log in to file
    *
    * TODO see:
    * Log the operations in to file
    * http://www.w3.org/Daemon/User/Config/Logging.html#common-logfile-format
    *
    * @package Phamm
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $pn
    * @param string $user
    * @param string $operation
    * @param bool $result
    **/
    public function phamm_log ($pn,$user,$operation,$result)
    {
        if (PHAMM_LOG == 1)
        {
            if (!$pn)
                $pn = 'phamm';

            if ($result)
                $this->resultLabel = 'OK';
            else
                $this->resultLabel = 'FAILED';

            // Set the file in Append mode
            $this->logFile = fopen (LOG_FILE,'a');

            // Prepare the log string
            $this->log_row = "$this->ip - $user [$this->day $this->hour] \"$pn : $operation\" $this->resultLabel\n";

            // Write the log in to file
            fwrite ($this->logFile,$this->log_row);

            // Close the file
            fclose ($this->logFile);
        }

        return true;
    }
//
}
