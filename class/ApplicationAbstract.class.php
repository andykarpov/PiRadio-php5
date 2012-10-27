<?php

class ApplicationAbstract {

    /**
     * @var array
     */
    public $cfg;

    public function __construct() {
        global $cfg;
        $this->cfg = $cfg;
    }

    protected function init() {

    }

    public function run() {

    }

    protected function main() {

    }
}

