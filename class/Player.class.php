<?php

class Player {
    
    protected $app;
    
    protected $mpd;
    
    protected $stations;
            
    protected $is_playing = false;
    
    public $current_index = 0;
    
    public $current_volume = 100;
    
    public function __construct(&$app) {
        $this->app = $app;
        $this->mpd = new MPD();
        $this->stations = new Stations($app);
    }
    
    public function init() {
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];
        
        // init MPD
        $this->mpd->setHost($env['mpd']['host']);
        $this->mpd->setPort($env['mpd']['port']);
        $this->mpd->setPassword($env['mpd']['password']);

        $this->mpd->init();
        $this->stations->init();
        $this->setPlaylist();
        $this->loadState();        
    }
    
    public function saveState() {
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];        
        file_put_contents($env['state'], (string) $this->current_index . ':' . (string) $this->current_volume);
        return true;
    }
    
    public function loadState() {
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];
        $state = file_get_contents($env['state']);
        $state = explode(':', $state);
        if (isset($state[0]) && isset($state[1])) {
            $this->current_index = (int) $state[0];
            $this->current_volume = (int) $state[1];
            $this->app->serial->sendLine("SET_ENC:" . (string)($this->current_index*4));
            return true;
        }
        return false;
    }
    
    public function setPlaylist() {
        $all = $this->stations->getAll();
        $this->mpd->stop();
        $this->mpd->playlistClear();
        foreach($all as $station) {
            $this->mpd->playlistAdd($station->getUrl());            
        }
    }
    
    protected function fadeIn() {
        // fade from 20 to $this->current_volume
        for ($i=20; $i<=$this->current_volume; $i=$i+1) {
            $this->mpd->setVolume($i);
            usleep(5000);
        }
    }
    
    protected function fadeOut() {
        // fade from $this->current_volume to 20
        for ($i=$this->current_volume; $i>=20; $i=$i-1) {
            $this->mpd->setVolume($i);
            usleep(5000);
        }
    }
    
    public function play($index) {
        if ($this->stations->getStation($index) instanceof Station) {
            $this->mpd->skipTo($index);
            $this->mpd->play();
            $this->fadeIn();
            $this->is_playing = true;
            $this->current_index = $index;
        } else {
            $this->current_index = 0;
        }
        $this->saveState();
    }
    
    public function stop() {
        $this->fadeOut();
        $this->mpd->stop();
        $this->is_playing = false;
    }
    
    public function getIsPlaying() {
        return $this->is_playing;
    }
    
    public function getStationsCount() {
        return $this->stations->getCount();
    }
    
    public function getCurrentIndex() {
        return $this->current_index;
    }

    public function getCurrentVolume() {
        return $this->current_volume;
    }
    
    public function setVolume($volume) {
        $this->current_volume = (int)$volume;
        
        if ($this->current_volume < 0) $this->current_volume = 0;
        if ($this->current_volume > 100) $this->current_volume = 100;
        $this->saveState();
        return $this->mpd->setVolume($this->current_volume);
    }
    
    public function getStation($index) {
        return $this->stations->getStation($index);
    }
}