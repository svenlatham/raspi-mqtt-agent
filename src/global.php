<?php


function getDeviceId() {
    // Raspberry Pi
    $serial = 'unknown';
    $fp = popen("cat /proc/cpuinfo",'r');
    $data = fread($fp, 4096);
    pclose($fp);
    $lines = explode("\n", $data);
    foreach($lines as $line) {
      if (preg_match("/Serial\s*: ([a-zA-Z0-9]+)/", $line, $matches)) {
        $serial = $matches[1];
      }
    }
    return trim($serial);
  }
  