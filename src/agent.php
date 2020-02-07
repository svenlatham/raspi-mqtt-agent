<?php
require_once '../src/global.php';
require_once '../lib/phpmqtt/phpMQTT.php';
$configFile = '/etc/raspi-mqtt-agent.conf';

if (!file_exists($configFile)) {
    throw new Exception("Config file needs to be created");
}

// Get Raspberry Pi device ID:
$device = getDeviceId();

$config = json_decode(file_get_contents($configFile));
$client_id = sprintf('client_%s', uniqid());

$mqtt = new \Bluerhinos\phpMQTT($config->server, $config->port, $client_id);
if (!$mqtt->connect(true, NULL, $config->username, $config->password)) {
    throw new Exception("Could not connect");
}

$topics["devices/{$device}/#"] = array("qos" => 0, "function" => "procmsg");


$mqtt->subscribe($topics, 0);
$timer = 0;
$states = array();
$states[0] = null;
$states[7] = null;
$states[8] = null;
$states[9] = null;

while ($mqtt->proc()) {
    if ($timer <= 0) {
        $mqtt->publish("devices/{$device}/ping", date('c'), 0, 1);
        $timer = 30;
    }
    $timer--;
    sleep(1);
    foreach(array_keys($states) as $k) {
        $cmd = sprintf("gpio read %d", $k);
        $val = (int)(shell_exec($cmd));
        //printf("States %s is %s %s, val is %s %s\n", $k, gettype($states[$k]), $states[$k], gettype($val), $val);
        if ($val !== $states[$k]) {
            $states[$k] = (int)$val;
            $path = sprintf("devices/%s/gpio/%d/state", $device, $k);
            $mqtt->publish($path, (int)$val, 0, 1);
        }

    }
}

$mqtt->close();

function procmsg($topic, $msg)
{
    //printf("[%s] %s - %s\n", $topic, date('Y-m-d H:i:s'), $msg);
    if (preg_match("/^devices\/([a-zA-Z0-9]+)\/gpio\/(\d+)\/set$/", $topic, $matches)) {
        $devicecheck = $matches[1];
        if (!$devicecheck != getDeviceId()) {
            // Ignore... although why are we getting this?
        }
        $gpio = $matches[2];
        $data = json_decode($msg);
        $cmd = sprintf("gpio mode %d out", $gpio);
        system($cmd, $output); 
        printf("Interpreted as %s %s\n", gettype($data), $data);
        // Remember, this is opposites day:
        switch ($data) {
            case 1:
                $cmd = sprintf("gpio write %d %d", $gpio, 1);
                printf("Running %s\n", $cmd);
                system($cmd, $output);
                break;
            case 0:
                $cmd = sprintf("gpio write %d %d", $gpio, 0);
                printf("Running %s\n", $cmd);
                system($cmd, $output);
                break;
        }
    }
}
