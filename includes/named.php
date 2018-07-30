<?php
class named {
  private $tmp;
  private $file;

  public function __construct(string $tmp,string $file) {
    $this->tmp = $tmp;
    $this->file = $file;
  }

  public function checkTmp() {

    $cmd = "/usr/sbin/named-checkconf {tmpfile}";
    $cmd = str_replace("{tmpfile}",escapeshellarg($this->tmp ),$cmd);
    exec($cmd,$log,$exit);

    if($exit === 0) {
      return true;
    } else {
      return false;
    }
  }

  public function reload() {
    $cmd = "/usr/sbin/rndc reload";
    exec($cmd,$log,$exit);

    if($exit === 0) {
      return true;
    } else {
      return false;
    }
  }

  public function writeConf($data) {
    if(file_put_contents($this->tmp,$data)) {
      if($this->checkTmp()) {

        /* rename() changes permission of configuration file */
        /* rename() changes permission of configuration file */
        $validData = file_get_contents($this->tmp);
        if(file_put_contents($this->file,$validData)) {
          return true;
        }

      }
    }

    return false;
  }
}
?>
