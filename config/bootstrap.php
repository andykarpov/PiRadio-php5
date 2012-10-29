<?php

ini_set('include_path', ini_get('include_path').':..');

date_default_timezone_set('Europe/Kiev');

error_reporting(E_ALL);

require_once 'class/Debug.class.php';
require_once 'lib/PhpSerial/PhpSerial.class.php';
require_once 'class/ApplicationAbstract.class.php';
require_once 'class/ApplicationCli.class.php';
require_once 'class/LCD.class.php';
require_once 'class/M3U.class.php';
require_once 'class/MPD.class.php';
require_once 'class/Process.class.php';
require_once 'class/Player.class.php';
require_once 'class/Serial.class.php';
require_once 'class/Stations.class.php';
require_once 'class/Station.class.php';
require_once 'class/ScreenAbstract.class.php';
require_once 'class/ScreenPlay.class.php';
require_once 'class/ScreenVolume.class.php';
include 'config/config.php';

