<?php
class zone {

  public $dir;
  public $file;
  public $signed;
  public $tmp;
  public $zone;
  public $log;

  public static function dnsNameReverse($zone) {
    $zone 	= trim($zone,".");
    $part 		= explode(".",$zone);
    $reverseZone 	= "";

    for($i=sizeof($part)-1; $i >= 0;$i--) {
      $reverseZone .= $part[$i] .".";
    }

    $reverseZone = trim($reverseZone,".");
    return $reverseZone;
  }

  public function __construct(string $zone,log $log) {
    $this->dir = bindroot . zone::dnsNameReverse($zone);
    $this->file	= $this->dir ."/zone";
    $this->signed	= $this->dir ."/zone.signed";
    $this->tmp = $this->dir ."/tmp";
    $this->zone = $zone;
    $this->log = $log;

    if(file_exists($this->dir) && !is_dir($this->dir)) {
      return false;
    } elseif(!is_dir($this->dir)) {
      if(!mkdir($this->dir,0750)) {
        return false;
      }
    }
  }

  private function checkZoneFile($zone,$file) {
    $oldDir = getcwd();
    chdir($this->dir);
    $cmd = "/usr/sbin/named-checkzone {zonename} {tempfile}";
    $cmd = str_replace("{zonename}",escapeshellarg($zone),$cmd);
    $cmd = str_replace("{tempfile}",escapeshellarg($file),$cmd);
    exec($cmd,$log,$exit);
    chdir($oldDir);

    if($exit === 0) {
      return true;
    } else {
      return false;
    }
  }

  public function checkZone() {
    return $this->checkZoneFile($this->zone,$this->file);
  }

  public function checkSignedZone() {
    return $this->checkZoneFile($this->zone,$this->signed);
  }

  public function checkTmp() {
    return $this->checkZoneFile($this->zone,$this->tmp);
  }

  public function signZone() {
    $oldDir = getcwd();
    chdir($this->dir);

    $cmd = "/usr/sbin/dnssec-signzone -A -3 $(head -c 1000 /dev/random | sha1sum | cut -b 1-16) -N INCREMENT -o {zonename} -t {zonefile}";
    $cmd = str_replace("{zonename}",escapeshellarg($this->zone),$cmd);
    $cmd = str_replace("{zonefile}",escapeshellarg($this->file),$cmd);
    exec($cmd,$log,$exit);
    chdir($oldDir);

    if($exit == 0) {
      return true;
    } else {
      return false;
    }
  }

  public function writeZone($data) {
    if(file_put_contents($this->tmp,$data)) {

      if($this->checkTmp()) {
        if(is_file($this->file)) {
          unlink($this->file);
        }

        if(rename($this->tmp,$this->file)) {
          return true;
        }
      } else {
        $this->log->warning("File not ok.");
      }
    } else {
      $this->log->warning("Data not written");
    }

    return false;
  }
}
?>
