<?php

abstract class ScreenAbstract {

    /**
     * @var ApplicationCli
     */
    protected $app;

    /**
     * @param ApplicationCli $app
     */
    public function __construct(&$app) {
        $this->app = $app;
    }

    public function init() {

    }
    
    public function process() {
        
    }
    
    public function action($time) {
        
    }
}