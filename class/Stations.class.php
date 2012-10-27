<?php

class Stations {

    /**
     * @var ApplicationCli
     */
    protected $app;

    /**
     * @var Station[]
     */
    protected $stations = array();

    /**
     * @var M3U
     */
    protected $m3u;

    /**
     * Class constructor
     *
     * @param ApplicationCli $app
     */
    public function __construct(&$app) {
        $this->app = $app;
    }

    /**
     * Init
     *
     */
    public function init() {
        $env = $env = $this->app->cfg['environment'][$this->app->cfg['env']];
        $this->m3u = new M3U($env['playlist']);
        $this->load();
    }

    /**
     * Load stations from m3u playlist
     *
     * @return bool
     */
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

    /**
     * Save stations to the m3u playlist
     *
     * @return mixed
     */
    public function save() {
        $this->m3u->setStations($this->getAll());
        return $this->m3u->save();
    }

    /**
     * Add new station
     *
     * @param Station $station
     * @return bool
     */
    public function addStation(Station $station) {
        $this->stations[] = $station;
        return true;
    }

    /**
     * Delete station
     *
     * @param integer $index
     * @return bool
     */
    public function deleteStation($index) {
        if (isset($this->stations[$index])) {
            unset($this->stations[$index]);
            $this->renumberStations();
            return true;
        }
        return false;
    }

    /**
     * Update station
     *
     * @param integer $index
     * @param Station $station
     * @return bool
     */
    public function updateStation($index, Station $station) {
        if (!isset($this->stations[$index])) return false;
        $this->stations[$index] = $station;
        return true;
    }

    /**
     * Re-index stations array
     *
     */
    protected function renumberStations() {
        $new = array();
        foreach($this->stations as $station) {
            $new[] = $station;
        }
        $this->stations = $new;
    }

    /**
     * Return stations count
     *
     * @return int
     */
    public function getCount() {
        return count($this->stations);
    }

    /**
     * Return station at $index
     *
     * @param integer $index
     * @return Station|null
     */
    public function getStation($index) {
        if (isset($this->stations[$index])) {
            return $this->stations[$index];
        }
        return null;
    }

    /**
     * Return array of stations
     *
     * @return Station[]
     */
    public function getAll() {
        return $this->stations;
    }

    /**
     * Return array of station names
     *
     * @return array
     */
    public function getNames() {
        $names = array();
        foreach($this->stations as $station) {
            $names[] = $station->getName();
        }
        return $names;
    }
}
