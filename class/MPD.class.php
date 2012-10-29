<?php

/**
 * Class to talk mpd via mpc binary calls
 *
 */
class MPD {

    /**
     * mpc binary path
     *
     * @var mixed
     */
    protected $mpc = null;

    /**
     * mpd hostname
     *
     * @var string
     */
    protected $host = 'localhost';

    /**
     * mpd port
     *
     * @var int
     */
    protected $port = 6600;

    /**
     * mpd password
     *
     * @var mixed
     */
    protected $password = null;

    /**
     * Class constructor
     *
     */
    public function __construct() {

    }

    /**
     * Executes a mpc command
     *
     * @param string $cmd command
     * @param array $args array of arguments
     * @return string
     */
    protected function execCommand($cmd, $args = array()) {
        $result = '';

        foreach($args as $i => $arg) {
            $args[$i] = escapeshellarg($arg);
        }

        $options = '';
        $options .= ' --host ' . (($this->password) ? escapeshellarg($this->password . '@' . $this->host) : escapeshellarg($this->host));
        $options .= ' --port ' . (int) $this->port;

        Process::forkProcess(
            $this->mpc . ' ' . $options . ' ' . $cmd . ' ' . implode(' ', $args),
            $result
        );

        return $result;
    }

    /**
     * Set mpc executable path
     *
     * @param string $filename
     */
    public function setMpcExecutable($filename) {
        $this->mpc = $filename;
    }

    /**
     * Set mpd hostname
     *
     * @param string $host
     */
    public function setHost($host) {
        $this->host = $host;
    }

    /**
     * Set mpd port
     *
     * @param string $port
     */
    public function setPort($port) {
        $this->port = $port;
    }

    /**
     * Set mpd password
     *
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * Sets the mixer volume to <newVol>, which should be between 1 - 100.
     *
     * @param integer $newVol
     * @return boolean
     * @throws Exception
     */
    public function setVolume($newVol) {

        if ( ! is_numeric($newVol) ) {
            throw new Exception("setVolume() : argument 1 must be a numeric value");
        }

        // Forcibly prevent out of range errors
        if ( $newVol < 0 )   $newVol = 0;
        if ( $newVol > 100 ) $newVol = 100;

        $this->execCommand('volume', array($newVol));

        return true;
    }

    /**
     * Adds the file <file> to the end of the playlist. <file> must be a track in the MPD database.
     *
     * @param string $fileName
     * @return boolean
     */
    public function playlistAdd($fileName) {
        $this->execCommand('add', array($fileName));
        return true;
    }

    /**
     * Empties the playlist.
     *
     * @return boolean
     */
    public function playlistClear() {
        $this->execCommand('clear');
        return true;
    }

    /**
     * Moves track number <origPos> to position <newPos> in the playlist. This is used to reorder
     * the songs in the playlist.
     *
     * @param integer $origPos
     * @param integer $newPos
     * @return boolean
     */
    public function playlistMoveTrack($origPos, $newPos) {
        $this->execCommand('move', array($origPos, $newPos));
        return true;
    }

    /**
     * Removes track <id> from the playlist.
     *
     * @param integer $id
     * @return boolean
     */
    public function playlistRemoveTrack($id) {
        $this->execCommand('del', array($id));
        return true;
    }

    /**
     * Begins playing the songs in the MPD playlist.
     *
     * @param integer $index
     * @return boolean
     */
    public function play($index = 1) {
        $this->execCommand('play', array($index));
        return true;
    }

    /**
     * Stops playing the MPD.
     *
     * @return boolean
     */
    public function stop() {
        $this->execCommand('stop');
        return true;
    }

    /**
     * Toggles pausing on the MPD. Calling it once will pause the player, calling it again
     * will unpause.
     *
     * @return boolean
     */
    public function pause() {
        $this->execCommand('pause');
        return true;
    }

    /**
     * Return status response
     *
     * Response format: <song title> >>> <station name>
     *
     * @return string
     */
    public function getStatus() {
        return $this->execCommand("status --format '%title% >>> %name%'");
    }
}
