<?php

class Station {
    
    protected $name;
    protected $url;
    
    public function __construct($name, $url) {
        $this->setName($name);
        $this->setUrl($url);
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getUrl() {
        return $this->url;
    }
    
    public function setName($name) {
        $this->name = (string) $name;
        return $this;
    }
    
    public function setUrl($url) {
        $this->url = (string) $url;
        return $this;
    }
    
}