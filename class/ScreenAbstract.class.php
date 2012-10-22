<?php

abstract class ScreenAbstract {
    
    protected $app;
    
    public function __construct(&$app) {
        $this->app = $app;
    }
    
    public function process() {
        
    }
    
    public function action($time) {
        
    }
}