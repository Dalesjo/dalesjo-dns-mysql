<?php

class log {
  private $debug = false;

  public function __construct(bool $debug) {
    $this->debug = $debug;
  }

  public function warning($message) {
    $this->log(LOG_WARNING,$message);
  }

  public function error($message) {
    $this->log(LOG_ERR,$message);
  }

  public function info($message) {
    $this->log(LOG_INFO,$message);
  }

  private function log(int $priority,string $message) {
    if($this->debug) {
      echo "Debug: ".$message."\n";
    } else {
      syslog($priority,$message);
    }
  }

}

?>
