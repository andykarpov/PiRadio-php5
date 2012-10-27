<?php

class M3U {
    
    protected $raw = array();
    protected $filename;
    
    public function __construct($filename) {
        $this->setFilename($filename);
    }
    
    public function setFilename($filename) {
        $this->filename = $filename;
        return $this;
    }
    
    public function getFilename() {
        return $this->filename;
    }
    
    public function load() {
        if (!file_exists($this->filename)) return false;
        $this->raw = file($this->filename);
        if (empty($this->raw[0]) or strpos($this->raw[0], '#EXTM3U') !== 0) {
            $this->raw = array();
            return false;
        }
        return true;
    }
    
    public function getStations() {
        $stations = array();
        foreach($this->raw as $i => $row) {
            if (strpos($row, '#EXTM3U') === 0) continue;
            if (!(strpos($row, '#EXTINF:') === 0)) continue;
            $parsed = array();
            if (preg_match('@#EXTINF:([-0-9]+),(.*)@', $row, $parsed)) {
                if (isset($parsed[2]) and isset($this->raw[$i+1])) {
                    $stations[] = new Station(trim($parsed[2]), trim($this->raw[$i+1]));
                }
            }
        }
        return $stations;
    }
    
    public function setStations($stations) {
        $this->raw = array();
        $this->raw[] = '#EXTM3U';
        foreach($stations as $station) {
            $this->raw[] = '#EXTINF:-1,' . $station->name . ' -';
            $this->raw[] = $station->url;
        }
        return $this;
    }
    
    public function save() {
        if (!is_writeable($this->filename)) return false;
        return file_put_contents(
            $this->filename, 
            implode('\r\n', $this->raw)
        );
    }
}