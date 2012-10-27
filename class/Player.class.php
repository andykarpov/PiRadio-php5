<?php

class Player {

    /**
     * @var ApplicationCli
     */
    protected $app;

    /**
     * @var MPD
     */
    protected $mpd;

    /**
     * @var Stations
     */
    protected $stations;

    /**
     * @var bool
     */
    protected $is_playing = false;

    /**
     * @var int
     */
    public $current_index = 0;

    /**
     * @var int
     */
    public $current_volume = 100;

    protected $meta_information = array(
        'title' => '',
        'name' => ''
    );

    /**
     * Class constructor
     *
     * @param ApplicationCli $app
     */
    public function __construct(&$app) {
        $this->app = $app;
        $this->mpd = new MPD();
        $this->stations = new Stations($app);
    }

    /**
     * Init
     */
    public function init() {
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];
        
        // init MPD
        $this->mpd->setMpcExecutable($env['mpd']['mpc_bin']);

        $this->mpd->init();
        $this->stations->init();
        $this->setPlaylist();
        $this->loadState();
    }

    /**
     * Save the current playing station and volume into the filesystem state file
     *
     * @return bool
     */
    public function saveState() {
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];
        file_put_contents($env['state'], (string) $this->current_index . ':' . (string) $this->current_volume);
        return true;
    }

    /**
     * Load the current playing station and volume from the state file
     *
     * @return bool
     */
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

    /**
     * Clear and populate mpd playlist with stations from $this->stations
     *
     */
    public function setPlaylist() {
        $all = $this->stations->getAll();
        $this->mpd->stop();
        $this->mpd->playlistClear();
        foreach($all as $station) {
            $this->mpd->playlistAdd($station->getUrl());
        }
    }

    /**
     * Make fadeIn volume effect
     */
    protected function fadeIn() {
        // fade from 20 to $this->current_volume
        for ($i=20; $i<=$this->current_volume; $i=$i+1) {
            $this->mpd->setVolume($i);
            usleep(5000);
        }
    }

    /**
     * Make fadeOut volume effect
     */
    protected function fadeOut() {
        // fade from $this->current_volume to 20
        for ($i=$this->current_volume; $i>=20; $i=$i-1) {
            $this->mpd->setVolume($i);
            usleep(5000);
        }
    }

    /**
     * Play station at $index
     *
     * @param $index
     */
    public function play($index) {
        if ($this->stations->getStation($index) instanceof Station) {
            $this->mpd->play($index+1);
            //$this->fadeIn();
            $this->is_playing = true;
            $this->current_index = $index;
        } else {
            $this->current_index = 0;
        }
        $this->saveState();
    }

    /**
     * Stop current playing station
     */
    public function stop() {
        //$this->fadeOut();
        $this->mpd->stop();
        $this->is_playing = false;
    }

    /**
     * Return true if current playing state is true
     *
     * @return bool
     */
    public function getIsPlaying() {
        return $this->is_playing;
    }

    /**
     * Return number of stations in the current playlist
     *
     * @return int
     */
    public function getStationsCount() {
        return $this->stations->getCount();
    }

    /**
     * Return current station index
     *
     * @return int
     */
    public function getCurrentIndex() {
        return $this->current_index;
    }

    /**
     * Return current volume
     *
     * @return int
     */
    public function getCurrentVolume() {
        return $this->current_volume;
    }

    /**
     * Set current volume
     *
     * @param $volume
     * @return bool
     */
    public function setVolume($volume) {
        $this->current_volume = (int)$volume;
        
        if ($this->current_volume < 0) $this->current_volume = 0;
        if ($this->current_volume > 100) $this->current_volume = 100;
        $this->saveState();
        return $this->mpd->setVolume($this->current_volume);
    }

    /**
     * Get station at $index
     *
     * @param $index
     * @return null|Station
     */
    public function getStation($index) {
        return $this->stations->getStation($index);
    }

    protected function fetchMetaInformation() {

        // todo: call only once per N seconds

        /*$res = $this->mpd->getSongInfo();
        $lines = explode("\n", $res);
        if (!empty($lines)) {
            //
        }*/
    }

    public function getCurrentTitle() {
        $this->fetchMetaInformation();
        return $this->meta_information['title'];
    }

    public function getCurrentSong() {
        $this->fetchMetaInformation();
        return $this->meta_information['name'];
    }
}