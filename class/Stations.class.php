<?php

class Stations {
    
    protected $app;
    protected $stations = array();
    protected $m3u;
    
    public function __construct(&$app) {
        $this->app = $app;
    }
    
    public function init() {        
        $env = $env = $this->app->cfg['environment'][$this->app->cfg['env']];        
        $this->m3u = new M3U($env['playlist']);
        $this->load();
    }
    
    public function load() {
        $this->stations = array();
        if (!$this->m3u->load()) return false;
        $stations = $this->m3u->getStations();
        if (!$stations)  return false;
        foreach($stations as $station) {
            $this->addStation($station);
        }
        return true;
    }
    
    public function save() {
        $this->m3u->setStations($this->getAll());
        return $this->m3u->save();
    }
    
    public function addStation(Station $station) {
        $this->stations[] = $station;
        return true;
    }
    
    public function deleteStation($index) {
        if (isset($this->stations[$index])) {
            unset($this->stations[$index]);
            $this->renumberStations();
            return true;
        }
        return false;
    }
    
    public function updateStation($index, Station $station) {
        if (!isset($this->stations[$index])) return false;
        $this->stations[$index] = $station;
        return true;
    }
    
    protected function renumberStations() {
        $new = array();
        foreach($this->stations as $station) {
            $new[] = $station;
        }
        $this->stations = $new;
    }
    
    public function getCount() {
        return count($this->stations);
    }
    
    public function getStation($index) {
        if (isset($this->stations[$index])) {
            return $this->stations[$index];
        }
        return null;
    }
    
    public function getAll() {
        return $this->stations;
    }
    
    public function getNames() {
        $names = array();
        foreach($this->stations as $station) {
            $names[] = $station->name;
        }
        return $names;
    }
}
