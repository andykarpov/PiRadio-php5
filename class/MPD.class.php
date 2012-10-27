<?php

class MPD {

    protected $mpc = null;

    public function __construct() {

    }

    protected function execCommand($cmd, $args = array()) {
        $result = '';

        foreach($args as $i => $arg) {
            $args[$i] = escapeshellarg($arg);
        }

        // todo: allows to specify --h, --p, etc for remote mpd access
        $options = '';

        Process::forkProcess(
            $this->mpc . ' ' . $options . ' ' . escapeshellarg($cmd) . ' ' . implode(' ', $args),
            $result
        );

        return $result;
    }

    public function init() {

    }

    public function setMpcExecutable($filename) {
        $this->mpc = $filename;
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

    public function getSongInfo() {
        // todo
        return $this->execCommand('echo "currentsong" | nc localhost 6600 | grep -e "^Title: " -e "^Name: "');
    }
}
