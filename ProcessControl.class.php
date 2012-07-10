<?php

class ProcessControl
{
  /*
   * @param string $command  the command to execute
   * @param boolean $concurrent(=false) whether to execute it on the background
   * @param int $timeout(=60) the number of seconds to let the process live before killing it
   * @param integer $sleep(=2) the number of seconds of interval for checking the process is still running
   *
   * @return boolen - true on correct execution, false otherwise
   */
  public static function exec($command, $concurrent = false, $timeout = 60, $sleep = 2)
  {
    $pid = self::processExec($command, $concurrent, $sleep);

    if( $pid === false )
        return false;

    $cur = 0;
    // Second, loop for $timeout seconds checking if process is running
    while( $cur < $timeout ) {
        sleep($sleep);
        $cur += $sleep;
        // If process is no longer running, return true;

       echo "\n ---- $cur ------ \n";

        if( !self::processExists($pid) )
            return true; // Process must have exited, success!
    }

    // If process is still running after timeout, kill the process and return false
    self::processKill($pid);
    return false;
  }

  /*
   * @param string $commandJob  the command to execute
   * @param boolean $concurrent(=false) whether to execute it on the background
   * @param integer $sleep(=2) the number of seconds of interval for checking the process is still running
   *
   * @return boolen|integer - the pid on success, false otherwise
   */
  private static function processExec($commandJob, $concurrent = false, $sleep = 2)
  {
    $command = $commandJob.' > /dev/null 2>&1 & echo $!';
    exec($command ,$op);
    $pid = (int)$op[0];

    if (!$concurrent)
    {
      while(self::processExists($pid))
      {
        sleep($sleep);
      }
    }

    if($pid!="") return $pid;

    return false;
  }

  /*
   * @param integer $pid
   * @return bool - true on success
   */
  private static function processExists($pid)
  {
    exec("ps ax | grep $pid 2>&1", $output);

    while( list(,$row) = each($output) ) {
      $row_array = explode(" ", $row);
      $check_pid = $row_array[0];

      if($pid == $check_pid) {
              return true;
      }
    }

    return false;
  }

  /*
   * @param integer $pid
   * @return string
   */
  private static function processKill($pid)
  {
    sfErrorNotifier::alert("Gbeans: killed the process with id $pid at "  . date('H:i:s'));
    exec("kill -9 $pid", $output);
  }
}