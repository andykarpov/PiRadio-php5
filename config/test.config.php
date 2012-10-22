<?php

$cfg['environment']['test'] = array(
    'device' => '/dev/cu.usbserial-A50059JB',
    'baud_rate' => 9600,
    'parity' => 'none',
    'char_length' => 8,
    'stop_bits' => 1,
    'flow_control' => 'none',

    'mpd' => array(
        'host' => '192.168.2.16',
        'port' => 6600,
        'password' => 'admin'
    ),
    
    'playlist' => realpath(dirname(__FILE__) . '/../') . '/playlist/radio.m3u',
    'state' => realpath(dirname(__FILE__) . '/../') . '/state/current_state.txt'
);
