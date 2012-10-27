<?php

$cfg['environment']['production'] = array(
    'device' => '/dev/ttyUSB0',
    'baud_rate' => 9600,
    'parity' => 'none',
    'char_length' => 8,
    'stop_bits' => 1,
    'flow_control' => 'none',

    'mpd' => array(
        'mpc_bin' => '/usr/bin/mpc',
        'host' => 'localhost',
        'port' => 6600,
        'password' => null,
    ),
    
    'playlist' => realpath(dirname(__FILE__) . '/../') . '/playlist/radio.m3u',
    'state' => realpath(dirname(__FILE__) . '/../') . '/state/current_state.txt'
);
