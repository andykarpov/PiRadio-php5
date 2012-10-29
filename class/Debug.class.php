<?php

class Debug {

    public static function log($msg) {

        global $cfg;
        if (!empty($cfg['environment'][$cfg['env']]['debug'])) {
            echo date('Y-m-d H:i:s') . ': ' . (string) $msg . "\n";
        }
    }

}