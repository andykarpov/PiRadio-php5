<?php

/**
 * PHP Object Interface to the MPD Music Player Daemon
 * Version 2.0, Released 27/09/2012
 *
 * Copyright (C) 2003-2004  Benjamin Carlisle (bcarlisle@24oz.com)
 * http://mpd.24oz.com/ | http://www.musicpd.org/
 *
 * Changes added by Andrey Karpov <andy.karpov@gmail.com> to support php 5.3.
 * Also changed some names, visibility properties and excluded some notices and warnings.
 * All error reporting refactored to throwing Exceptions instead of triggering errors.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class MPD {

    // common command definitions for MPD to use
    const CMD_STATUS = "status";
    const CMD_STATISTICS = "stats";
    const CMD_VOLUME = "volume";
    const CMD_SETVOL = "setvol";
    const CMD_PLAY = "play";
    const CMD_STOP = "stop";
    const CMD_PAUSE = "pause";
    const CMD_NEXT = "next";
    const CMD_PREV = "previous";
    const CMD_PLLIST = "playlistinfo";
    const CMD_PLADD = "add";
    const CMD_PLREMOVE = "delete";
    const CMD_PLCLEAR = "clear";
    const CMD_PLSHUFFLE = "shuffle";
    const CMD_PLLOAD = "load";
    const CMD_PLSAVE = "save";
    const CMD_KILL = "kill";
    const CMD_REFRESH = "update";
    const CMD_REPEAT = "repeat";
    const CMD_LSDIR = "lsinfo";
    const CMD_SEARCH = "search";
    const CMD_START_BULK = "command_list_begin";
    const CMD_END_BULK = "command_list_end";
    const CMD_FIND = "find";
    const CMD_RANDOM = "random";
    const CMD_SEEK = "seek";
    const CMD_PLSWAPTRACK = "swap";
    const CMD_PLMOVETRACK = "move";
    const CMD_PASSWORD = "password";
    const CMD_TABLE = "list";
    const CMD_SHUTDOWN = "shutdown";

    // Predefined MPD Response messages
    const RESPONSE_ERR = "ACK";
    const RESPONSE_OK = "OK";

    // MPD State Constants
    const STATE_PLAYING = "play";
    const STATE_STOPPED = "stop";
    const STATE_PAUSED = "pause";

    // MPD Searching Constants
    const SEARCH_ARTIST = "artist";
    const SEARCH_TITLE = "title";
    const SEARCH_ALBUM = "album";

    // MPD Cache Tables
    const TBL_ARTIST = "artist";
    const TBL_ALBUM = "album";


    // TCP/Connection variables
    protected $host;
    protected $port;
    protected $password;

    protected $mpd_sock   = null;
    protected $connected  = false;

    // MPD Status variables
    protected $mpd_version    = "(unknown)";

    protected $state;
    protected $current_track_position;
    protected $current_track_length;
    protected $current_track_id;
    protected $volume;
    protected $repeat;
    protected $random;

    protected $uptime;
    protected $playtime;
    protected $db_last_refreshed;
    protected $num_songs_played;
    protected $playlist_count;

    protected $num_artists;
    protected $num_albums;
    protected $num_songs;

    protected $playlist = array();

    // Misc Other Vars
    protected $mpd_class_version = "2.0";

    protected $debugging   = false;    // Set to true to turn extended debugging on.
    protected $errStr      = "";       // Used for maintaining information about the last error message

    protected $command_queue;          // The list of commands for bulk command sending

    /**
     * Command compatibility min table
     *
     * @var array
     */
    protected $COMPATIBILITY_MIN_TBL = array(
        self::CMD_SEEK => "0.9.1",
        self::CMD_PLREMOVE => "0.9.1",
        self::CMD_RANDOM => "0.9.1",
        self::CMD_PLSWAPTRACK => "0.9.1",
        self::CMD_PLMOVETRACK => "0.9.1",
        self::CMD_PASSWORD => "0.10.0",
        self::CMD_SETVOL => "0.10.0"
    );

    /**
     * Command compatibility max table
     *
     * @var array
     */
    protected $COMPATIBILITY_MAX_TBL = array(
        self::CMD_VOLUME  => "0.10.0"
    );

    /**
     * Builds the MPD object.
     *
     */
    public function __construct() {
    }

    /**
     * Set MPD host
     *
     * @param string $host
     */
    public function setHost($host) {
        $this->host = $host;
    }

    /**
     * Set MPD port
     *
     * @param string $port
     */
    public function setPort($port) {
        $this->port = $port;
    }

    /**
     * Set MPD access password
     *
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * Trying to initiate a connection to MPD server
     *
     * @throws Exception
     */
    public function init() {
        $resp = $this->connect();

        if ( is_null($resp) ) {
            throw new Exception("Could not connect");
        } else {
            list ( $this->mpd_version ) = sscanf($resp, self::RESPONSE_OK . " MPD %s\n");
            if ( ! is_null($this->password) ) {
                if ( is_null($this->sendCommand(self::CMD_PASSWORD, $this->password)) ) {
                    $this->connected = false;
                    throw new Exception("Bad password");
                }
                if ( is_null($this->refreshInfo()) ) { // no read access -- might as well be disconnected!
                    $this->connected = false;
                    throw new Exception('Password supplied does not have read access');
                }
            } else {
                if ( is_null($this->refreshInfo()) ) { // no read access -- might as well be disconnected!
                    $this->connected = false;
                    throw new Exception('Password required to access server');
                }
            }
        }
    }

    /**
     * Internal debug function
     *
     * @param string $msg
     */
    protected function debug($msg) {
        if ($this->debugging) {
            echo $msg . "\n";
        }
    }

    /**
     * Connects to the MPD server.
     *
     * This is called automatically upon object instantiation; you should not need to call this directly.
     * @return null|string
     * @throws Exception
     */
    protected function connect() {

        $this->debug("mpd->connect() / host: ".$this->host.", port: ".$this->port);

        $this->mpd_sock = fsockopen($this->host, $this->port, $errNo, $errStr, 10);
        if (!$this->mpd_sock) {
            throw new Exception ("Socket Error: $errStr ($errNo)");
        } else {
            while(!feof($this->mpd_sock)) {
                $response =  fgets($this->mpd_sock,1024);
                if (strncmp(self::RESPONSE_OK, $response, strlen(self::RESPONSE_OK)) == 0) {
                    $this->connected = true;
                    return $response;
                    break;
                }
                if (strncmp(self::RESPONSE_ERR, $response, strlen(self::RESPONSE_ERR)) == 0) {
                    throw new Exception("Server responded with: $response");
                }
            }
            // Generic response
            throw new Exception("Connection not available");
        }
    }

    /**
     * Sends a generic command to the MPD server.
     *
     * Several command constants are pre-defined for use (see self::CMD_* constant definitions)
     *
     * @param string $cmdStr command string
     * @param string $arg1 argument 1
     * @param string $arg2 argument 2
     * @return null|string
     * @throws Exception
     */
    public function sendCommand($cmdStr,$arg1 = "",$arg2 = "") {

        $this->debug("mpd->sendCommand() / cmd: ".$cmdStr.", args: ".$arg1." ".$arg2);

        if ( ! $this->connected ) {
            throw new Exception("mpd->sendCommand() / Error: Not connected");
        } else {
            // Clear out the error String
            $this->errStr = "";
            $respStr = "";

            // Check the command compatibility:
            if ( ! $this->_checkCompatibility($cmdStr) ) {
                return null;
            }

            if (strlen($arg1) > 0) $cmdStr .= " \"$arg1\"";
            if (strlen($arg2) > 0) $cmdStr .= " \"$arg2\"";
            if (!fputs($this->mpd_sock,"$cmdStr\n")) {
                $this->disconnect();
                $this->connect();
                // retry
                fputs($this->mpd_sock,"$cmdStr\n");
            }
            while(!feof($this->mpd_sock)) {
                $response = fgets($this->mpd_sock,1024);

                // An OK signals the end of transmission -- we'll ignore it
                if (strncmp(self::RESPONSE_OK, $response, strlen(self::RESPONSE_OK)) == 0) {
                    break;
                }

                // An ERR signals the end of transmission with an error! Let's grab the single-line message.
                if (strncmp(self::RESPONSE_ERR, $response, strlen(self::RESPONSE_ERR)) == 0) {
                    list ( $junk, $errTmp ) = explode(self::RESPONSE_ERR . " ",$response );
                    $this->errStr = strtok($errTmp,"\n");
                }

                if ( strlen($this->errStr) > 0 ) {
                    return null;
                }

                // Build the response string
                $respStr .= $response;
            }

            $this->debug("mpd->sendCommand() / response: '".$respStr."'");
        }
        return $respStr;
    }

    /**
     * Queues a generic command for later sending to the MPD server. The CommandQueue can hold
     * as many commands as needed, and are sent all at once, in the order they are queued, using
     * the SendCommandQueue() method. The syntax for queueing commands is identical to SendCommand().
     *
     * @param string $cmdStr
     * @param string $arg1
     * @param string $arg2
     * @return bool|null
     * @throws Exception
     */
    public function queueCommand($cmdStr, $arg1 = "", $arg2 = "") {

        $this->debug("mpd->queueCommand() / cmd: ".$cmdStr.", args: ".$arg1." ".$arg2);

        if ( ! $this->connected ) {
            throw new Exception("mpd->queueCommand() / Error: Not connected");
        } else {
            if ( strlen($this->command_queue) == 0 ) {
                $this->command_queue = self::CMD_START_BULK . "\n";
            }
            if (strlen($arg1) > 0) $cmdStr .= " \"$arg1\"";
            if (strlen($arg2) > 0) $cmdStr .= " \"$arg2\"";

            $this->command_queue .= $cmdStr ."\n";

            $this->debug("mpd->QueueCommand() / return");
        }
        return true;
    }

    /**
     * Sends all commands in the Command Queue to the MPD server.
     *
     * @see queueCommand()
     * @return null|string
     * @throws Exception
     */
    public function sendCommandQueue() {

        $this->debug("mpd->sendCommandQueue()");

        if ( ! $this->connected ) {
            throw new Exception("mpd->sendCommandQueue() / Error: Not connected");
        }
        $this->command_queue .= self::CMD_END_BULK . "\n";
        if ( is_null($respStr = $this->sendCommand($this->command_queue)) ) {
            return null;
        } else {
            $this->command_queue = null;
            $this->debug("mpd->sendCommandQueue() / response: '" . $respStr . "'");
        }
        return $respStr;
    }

    /**
     * Adjusts the mixer volume on the MPD by <modifier>, which can be a positive (volume increase),
     * or negative (volume decrease) value.
     *
     * @param integer $modifier
     * @return null|string
     * @throws Exception
     */
    public function adjustVolume($modifier) {

        $this->debug("mpd->adjustVolume()");

        if ( ! is_numeric($modifier) ) {
            throw new Exception("adjustVolume() : argument 1 must be a numeric value");
        }

        $this->refreshInfo();
        $newVol = $this->volume + $modifier;
        $ret = $this->setVolume($newVol);

        $this->debug("mpd->adjustVolume() / return");
        return $ret;
    }

    /**
     * Sets the mixer volume to <newVol>, which should be between 1 - 100.
     *
     * @param integer $newVol
     * @return null|string
     * @throws Exception
     */
    public function setVolume($newVol) {

        $this->debug("mpd->setVolume()");

        if ( ! is_numeric($newVol) ) {
            throw new Exception("setVolume() : argument 1 must be a numeric value");
        }

        // Forcibly prevent out of range errors
        if ( $newVol < 0 )   $newVol = 0;
        if ( $newVol > 100 ) $newVol = 100;

        // If we're not compatible with SETVOL, we'll try adjusting using VOLUME
        if ( $this->_checkCompatibility(self::CMD_SETVOL) ) {
            if ( ! is_null($ret = $this->sendCommand(self::CMD_SETVOL, $newVol))) $this->volume = $newVol;
        } else {
            $this->refreshInfo();     // Get the latest volume
            if ( is_null($this->volume) ) {
                return null;
            } else {
                $modifier = ( $newVol - $this->volume );
                if ( ! is_null($ret = $this->sendCommand(self::CMD_VOLUME,$modifier))) $this->volume = $newVol;
            }
        }

        $this->debug("mpd->setVolume() / return");
        return $ret;
    }

    /**
     * Retrieves a database directory listing of the <dir> directory and places the results into
     * a multidimensional array. If no directory is specified, the directory listing is at the
     * base of the MPD music path.
     *
     * @param string $dir
     * @return array|null
     */
    public function getDir($dir = "") {

        $this->debug("mpd->getDir()");

        $resp = $this->sendCommand(self::CMD_LSDIR, $dir);
        $dirlist = $this->_parseFileListResponse($resp);

        $this->debug("mpd->getDir() / return ".print_r($dirlist));

        return $dirlist;
    }

    /**
     * Adds each track listed in a single-dimensional <trackArray>, which contains filenames
     * of tracks to add, to the end of the playlist. This is used to add many, many tracks to
     * the playlist in one swoop.
     *
     * @param array $trackArray
     * @return null|string
     * @throws Exception
     */
    public function playlistAddBulk($trackArray) {

        $this->debug("mpd->playlistAddBulk()");

        if (!is_array($trackArray)) {
            throw new Exception('Input argument is not an array');
        }

        foreach($trackArray as $track) {
            $this->queueCommand(self::CMD_PLADD, $track);
        }
        $resp = $this->sendCommandQueue();
        $this->refreshInfo();

        $this->debug("mpd->playlistAddBulk() / return");

        return $resp;
    }

    /**
     * Adds the file <file> to the end of the playlist. <file> must be a track in the MPD database.
     *
     * @param string $fileName
     * @return null|string
     */
    public function playlistAdd($fileName) {

        $this->debug("mpd->playlistAdd()");

        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLADD, $fileName))) $this->refreshInfo();

        $this->debug("mpd->playlistAdd() / return");

        return $resp;
    }

    /**
     * Moves track number <origPos> to position <newPos> in the playlist. This is used to reorder
     * the songs in the playlist.
     *
     * @param integer $origPos
     * @param integer $newPos
     * @return null|string
     * @throws Exception
     */
    public function playlistMoveTrack($origPos, $newPos) {

        $this->debug("mpd->playlistMoveTrack()");

        if ( ! is_numeric($origPos) ) {
            throw new Exception("playlistMoveTrack(): argument 1 must be numeric");
        }

        if ( $origPos < 0 or $origPos > $this->playlist_count ) {
            throw new Exception("playlistMoveTrack(): argument 1 out of range");
        }

        if ( $newPos < 0 ) $newPos = 0;
        if ( $newPos > $this->playlist_count ) $newPos = $this->playlist_count;

        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLMOVETRACK, $origPos, $newPos)))
            $this->refreshInfo();

        $this->debug("mpd->playlistMoveTrack() / return");

        return $resp;
    }

    /**
     * Randomly reorders the songs in the playlist.
     *
     * @return null|string
     */
    public function playlistShuffle() {
        $this->debug("mpd->playlistShuffle()");
        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLSHUFFLE))) $this->refreshInfo();
        $this->debug("mpd->playlistShuffle() / return");
        return $resp;
    }

    /**
     * Retrieves the playlist from <file>.m3u and loads it into the current playlist.
     *
     * @param $file
     * @return null|string
     */
    public function playlistLoad($file) {
        $this->debug("mpd->playlistLoad()");
        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLLOAD, $file))) $this->refreshInfo();
        $this->debug("mpd->playlistLoad() / return");
        return $resp;
    }

    /**
     * Saves the playlist to <file>.m3u for later retrieval. The file is saved in the MPD playlist
     * directory.
     *
     * @param $file
     * @return null|string
     */
    public function playlistSave($file) {
        $this->debug("mpd->playlistSave()");
        $resp = $this->sendCommand(self::CMD_PLSAVE, $file);
        $this->debug("mpd->playlistSave() / return");
        return $resp;
    }

    /**
     * Empties the playlist.
     *
     * @return null|string
     */
    public function playlistClear() {
        $this->debug("mpd->playlistClear()");
        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLCLEAR))) $this->refreshInfo();
        $this->debug("mpd->playlistClear() / return");
        return $resp;
    }

    /**
     * Removes track <id> from the playlist.
     *
     * @param $id
     * @return null|string
     * @throws Exception
     */
    public function playlistRemoveTrack($id) {
        $this->debug("mpd->playlistRemoveTrack()");
        if ( ! is_numeric($id) ) {
            throw new Exception("playlistRemoveTrack() : argument 1 must be a numeric value");
        }
        if ( ! is_null($resp = $this->sendCommand(self::CMD_PLREMOVE, $id))) $this->refreshInfo();
        $this->debug("mpd->playlistRemove() / return");
        return $resp;
    }

    /**
     * Enables 'loop' mode -- tells MPD continually loop the playlist. The <repVal> parameter
     * is either 1 (on) or 0 (off).
     *
     * @param boolean $repVal
     * @return null|string
     */
    public function setRepeat($repVal) {
        $this->debug("mpd->setRepeat()");
        $rpt = $this->sendCommand(self::CMD_REPEAT, $repVal);
        $this->repeat = $repVal;
        $this->debug("mpd->setRepeat() / return");
        return $rpt;
    }

    /**
     * Enables 'randomize' mode -- tells MPD to play songs in the playlist in random order. The
     * <rndVal> parameter is either 1 (on) or 0 (off).
     *
     * @param boolean $rndVal
     * @return null|string
     */
    public function setRandom($rndVal) {
        $this->debug("mpd->setRandom()");
        $resp = $this->sendCommand(self::CMD_RANDOM, $rndVal);
        $this->random = $rndVal;
        $this->debug("mpd->setRandom() / return");
        return $resp;
    }

    /**
     * Shuts down the MPD server (aka sends the KILL command). This closes the current connection,
     * and prevents future communication with the server.
     *
     * @return null|string
     */
    public function shutdown() {
        $this->debug("mpd->shutdown()");
        $resp = $this->sendCommand(self::CMD_SHUTDOWN);

        $this->connected = false;
        unset($this->mpd_version);
        unset($this->errStr);
        unset($this->mpd_sock);

        $this->debug("mpd->shutdown() / return");
        return $resp;
    }

    /**
     * Tells MPD to rescan the music directory for new tracks, and to refresh the Database. Tracks
     * cannot be played unless they are in the MPD database.
     *
     * @return null|string
     */
    public function DBRefresh() {
        $this->debug("mpd->DBRefresh()");
        $resp = $this->sendCommand(self::CMD_REFRESH);

        // Update local variables
        $this->refreshInfo();

        $this->debug("mpd->DBRefresh() / return");
        return $resp;
    }

    /**
     * Begins playing the songs in the MPD playlist.
     *
     * @return null|string
     */
    public function play() {
        $this->debug("mpd->play()");
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_PLAY) )) $this->refreshInfo();
        $this->debug("mpd->play() / return");
        return $rpt;
    }

    /**
     * Stops playing the MPD.
     *
     * @return null|string
     */
    public function stop() {
        $this->debug("mpd->stop()");
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_STOP) )) $this->refreshInfo();
        $this->debug("mpd->stop() / return");
        return $rpt;
    }

    /**
     * Toggles pausing on the MPD. Calling it once will pause the player, calling it again
     * will unpause.
     *
     * @return null|string
     */
    public function pause() {
        $this->debug("mpd->pause()");
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_PAUSE) )) $this->refreshInfo();
        $this->debug("mpd->pause() / return");
        return $rpt;
    }

    /**
     * Skips directly to the <idx> song in the MPD playlist.
     *
     * @param $idx
     * @return int|string
     * @throws Exception
     */
    public function skipTo($idx) {
        $this->debug("mpd->skipTo()");
        if ( ! is_numeric($idx) ) {
            throw new Exception("skipTo() : argument 1 must be a numeric value");
        }
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_PLAY, $idx))) $this->refreshInfo();
        $this->debug("mpd->skipTo() / return");
        return $idx;
    }

    /**
     * Skips directly to a given position within a track in the MPD playlist. The <pos> argument,
     * given in seconds, is the track position to locate. The <track> argument, if supplied is
     * the track number in the playlist. If <track> is not specified, the current track is assumed.
     *
     * @param integer $pos
     * @param integer $track
     * @return int|string
     * @throws Exception
     */
    public function seekTo($pos, $track = -1) {
        $this->debug("mpd->seekTo()");
        if ( ! is_numeric($pos) ) {
            throw new Exception("seekTo() : argument 1 must be a numeric value");
        }
        if ( ! is_numeric($track) ) {
            throw new Exception("seekTo() : argument 2 must be a numeric value");
        }
        if ( $track == -1 ) {
            $track = $this->current_track_id;
        }

        if ( ! is_null($rpt = $this->sendCommand(self::CMD_SEEK, $track, $pos))) $this->refreshInfo();
        $this->debug("mpd->seekTo() / return");
        return $pos;
    }

    /**
     * Skips to the next song in the MPD playlist. If not playing, returns an error.
     *
     * @return null|string
     */
    public function next() {
        $this->debug("mpd->next()");
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_NEXT))) $this->refreshInfo();
        $this->debug("mpd->next() / return");
        return $rpt;
    }

    /**
     * Skips to the previous song in the MPD playlist. If not playing, returns an error.
     *
     * @return null|string
     */
    public function previous() {
        $this->debug("mpd->previous()");
        if ( ! is_null($rpt = $this->sendCommand(self::CMD_PREV))) $this->refreshInfo();
        $this->debug("mpd->previous() / return");
        return $rpt;
    }

    /**
     * Searches the MPD database. The search <type> should be one of the following:
     * SEARCH_ARTIST, SEARCH_TITLE, SEARCH_ALBUM
     *
     * The search <string> is a case-insensitive locator string. Anything that contains
     * <string> will be returned in the results.
     *
     * @param string $type
     * @param string $string
     * @return array|null
     * @throws Exception
     */
    public function search($type, $string) {
        $this->debug("mpd->search()");
        if ( $type != self::SEARCH_ARTIST and
            $type != self::SEARCH_ALBUM and
                $type != self::SEARCH_TITLE ) {
            throw new Exception("mpd->search(): invalid search type");
        } else {
            if ( is_null($resp = $this->sendCommand(self::CMD_SEARCH,$type, $string))) return null;
            $searchlist = $this->_parseFileListResponse($resp);
        }
        $this->debug("mpd->search() / return ".print_r($searchlist));
        return $searchlist;
    }

    /* Find()
      *
      *
      */

    /**
     * Find() looks for exact matches in the MPD database. The find <type> should be one of
     * the following: SEARCH_ARTIST, SEARCH_TITLE, SEARCH_ALBUM
     *
     * The find <string> is a case-insensitive locator string. Anything that exactly matches
     * <string> will be returned in the results.
     *
     * @param string $type
     * @param string $string
     * @return array|null
     * @throws Exception
     */
    public function find($type, $string) {
        $this->debug("mpd->find()");
        if ( $type != self::SEARCH_ARTIST and
            $type != self::SEARCH_ALBUM and
                $type != self::SEARCH_TITLE ) {
            throw new Exception("mpd->find(): invalid find type");
        } else {
            if ( is_null($resp = $this->sendCommand(self::CMD_FIND, $type, $string))) return null;
            $searchlist = $this->_parseFileListResponse($resp);
        }
        $this->debug("mpd->find() / return ".print_r($searchlist));
        return $searchlist;
    }

    /**
     * Closes the connection to the MPD server.
     */
    public function disconnect() {
        $this->debug("mpd->disconnect()");
        fclose($this->mpd_sock);

        $this->connected = false;
        unset($this->mpd_version);
        unset($this->errStr);
        unset($this->mpd_sock);
    }

    /**
     * Returns the list of artists in the database in an associative array.
     *
     * @return array|null
     */
    public function getArtists() {
        $this->debug("mpd->getArtists()");
        if ( is_null($resp = $this->sendCommand(self::CMD_TABLE, self::TBL_ARTIST))) return null;
        $arArray = array();

        $arLine = strtok($resp,"\n");
        $arName = "";
        $arCounter = -1;
        while ( $arLine ) {
            list ( $element, $value ) = explode(": ", $arLine);
            if ( $element == "Artist" ) {
                $arCounter++;
                $arName = $value;
                $arArray[$arCounter] = $arName;
            }

            $arLine = strtok("\n");
        }
        $this->debug("mpd->getArtists()");
        return $arArray;
    }

    /**
     * Returns the list of albums in the database in an associative array. Optional parameter
     * is an artist Name which will list all albums by a particular artist.
     *
     * @param null $ar
     * @return array|null
     */
    public function getAlbums( $ar = null) {
        $this->debug("mpd->getAlbums()");
        if ( is_null($resp = $this->sendCommand(self::CMD_TABLE, self::TBL_ALBUM, $ar ))) return null;
        $alArray = array();

        $alLine = strtok($resp,"\n");
        $alName = "";
        $alCounter = -1;
        while ( $alLine ) {
            list ( $element, $value ) = explode(": ",$alLine);
            if ( $element == "Album" ) {
                $alCounter++;
                $alName = $value;
                $alArray[$alCounter] = $alName;
            }

            $alLine = strtok("\n");
        }
        $this->debug("mpd->getAlbums()");
        return $alArray;
    }

    /**
     * Computes a compatibility value from a version string
     *
     * @param $verStr
     * @return int
     */
    protected function _computeVersionValue($verStr) {
        list ($ver_maj, $ver_min, $ver_rel ) = explode(".", $verStr);
        return ( 100 * $ver_maj ) + ( 10 * $ver_min ) + ( $ver_rel );
    }

    /**
     * Check MPD command compatibility against our internal table. If there is no version
     * listed in the table, allow it by default.
     *
     * @param string $cmd
     * @return bool
     */
    protected function _checkCompatibility($cmd) {

        // Check minimum compatibility
        $req_ver_low = isset($this->COMPATIBILITY_MIN_TBL[$cmd]) ? $this->COMPATIBILITY_MIN_TBL[$cmd] : false;
        $req_ver_hi = isset($this->COMPATIBILITY_MAX_TBL[$cmd]) ? $this->COMPATIBILITY_MAX_TBL[$cmd] : false;

        $mpd_ver = $this->_computeVersionValue($this->mpd_version);

        if ( $req_ver_low ) {
            $req_ver = $this->_computeVersionValue($req_ver_low);

            if ( $mpd_ver < $req_ver ) {
                $this->errStr = "Command '$cmd' is not compatible with this version of MPD, version ".$req_ver_low." required";
                return false;
            }
        }

        // Check maxmum compatibility -- this will check for deprecations
        if ( $req_ver_hi ) {
            $req_ver = $this->_computeVersionValue($req_ver_hi);

            if ( $mpd_ver > $req_ver ) {
                $this->errStr = "Command '$cmd' has been deprecated in this version of MPD.";
                return false;
            }
        }

        return true;
    }

    /**
     * Builds a multidimensional array with MPD response lists.
     *
     * NOTE: This function is used internally within the class. It should not be used.
     *
     * @param $resp
     * @return array|null
     */
    protected function _parseFileListResponse($resp) {
        if ( is_null($resp) ) {
            return null;
        } else {
            $plistArray = array();
            $plistLine = strtok($resp,"\n");
            $plistFile = "";
            $plCounter = -1;
            while ( $plistLine ) {
                list ( $element, $value ) = explode(": ",$plistLine);
                if ( $element == "file" ) {
                    $plCounter++;
                    $plistFile = $value;
                    $plistArray[$plCounter]["file"] = $plistFile;
                } else {
                    $plistArray[$plCounter][$element] = $value;
                }

                $plistLine = strtok("\n");
            }
        }
        return $plistArray;
    }

    /**
     * Updates all class properties with the values from the MPD server.
     *
     * NOTE: This function is automatically called upon Connect() as of v1.1.
     *
     * @return bool|null
     */
    public function refreshInfo() {
        // Get the Server Statistics
        $statStr = $this->sendCommand(self::CMD_STATISTICS);
        if ( !$statStr ) {
            return null;
        } else {
            $stats = array();
            $statLine = strtok($statStr,"\n");
            while ( $statLine ) {
                list ( $element, $value ) = explode(": ",$statLine);
                $stats[$element] = $value;
                $statLine = strtok("\n");
            }
        }

        // Get the Server Status
        $statusStr = $this->sendCommand(self::CMD_STATUS);
        if ( ! $statusStr ) {
            return null;
        } else {
            $status = array();
            $statusLine = strtok($statusStr,"\n");
            while ( $statusLine ) {
                list ( $element, $value ) = explode(": ",$statusLine);
                $status[$element] = $value;
                $statusLine = strtok("\n");
            }
        }

        // Get the Playlist
        $plStr = $this->sendCommand(self::CMD_PLLIST);
        $this->playlist = $this->_parseFileListResponse($plStr);
        $this->playlist_count = count($this->playlist);

        // Set Misc Other Variables
        $this->state = $status['state'];
        if ( ($this->state == self::STATE_PLAYING) || ($this->state == self::STATE_PAUSED) ) {
            $this->current_track_id = $status['song'];
            list ($this->current_track_position, $this->current_track_length ) = explode(":",$status['time']);
        } else {
            $this->current_track_id = -1;
            $this->current_track_position = -1;
            $this->current_track_length = -1;
        }

        $opts = array(
            'repeat' => 'repeat',
            'random' => 'random',
            'db_last_refreshed' => 'db_update',
            'volume' => 'volume',
            'uptime' => 'uptime',
            'playtime' => 'playtime',
            'num_songs_played' => 'songs_played',
            'num_artists' => 'num_artists',
            'num_songs' => 'num_songs',
            'num_albums' => 'num_albums'
        );

        foreach($opts as $opt => $name) {
            if (isset($status[$name])) $this->$opt = $status[$name];
        }

        return true;
    }

    /**
     * Retrieves the 'statistics' variables from the server and tosses them into an array.
     *
     * NOTE: This function really should not be used. Instead, use $this->[variable]. The function
     * will most likely be deprecated in future releases.
     *
     * @deprecated
     * @return array|null
     */
    public function getStatistics() {
        $this->debug("mpd->getStatistics()");
        $stats = $this->sendCommand(self::CMD_STATISTICS);
        if ( !$stats ) {
            return null;
        } else {
            $statsArray = array();
            $statsLine = strtok($stats,"\n");
            while ( $statsLine ) {
                list ( $element, $value ) = explode(": ",$statsLine);
                $statsArray[$element] = $value;
                $statsLine = strtok("\n");
            }
        }
        $this->debug("mpd->getStatistics() / return: " . print_r($statsArray));
        return $statsArray;
    }

    /**
     * Retrieves the 'status' variables from the server and tosses them into an array.
     *
     * NOTE: This function really should not be used. Instead, use $this->[variable]. The function
     * will most likely be deprecated in future releases.
     *
     * @deprecated
     * @return array|null
     */
    public function getStatus() {
        $this->debug("mpd->getStatus()");
        $status = $this->sendCommand(self::CMD_STATUS);
        if ( ! $status ) {
            return null;
        } else {
            $statusArray = array();
            $statusLine = strtok($status,"\n");
            while ( $statusLine ) {
                list ( $element, $value ) = explode(": ",$statusLine);
                $statusArray[$element] = $value;
                $statusLine = strtok("\n");
            }
        }
        $this->debug("mpd->getStatus() / return: " . print_r($statusArray));
        return $statusArray;
    }

    /**
     * Retrieves the mixer volume from the server.
     *
     * NOTE: This function really should not be used. Instead, use $this->volume. The function
     * will most likely be deprecated in future releases.
     *
     * @deprecated
     * @return null
     */
    public function getVolume() {
        $this->debug("mpd->getVolume()");
        $volLine = $this->sendCommand(self::CMD_STATUS);
        if ( ! $volLine ) {
            return null;
        } else {
            list ($vol) = sscanf($volLine,"volume: %d");
        }
        $this->debug("mpd->getVolume() / return: $vol");
        return $vol;
    }

    /**
     * Retrieves the playlist from the server and tosses it into a multidimensional array.
     *
     * This function really should not be used. Instead, use $this->playlist. The function
     * will most likely be deprecated in future releases.
     *
     * @deprecated
     * @return array|null
     */
    public function getPlaylist() {

        $this->debug("mpd->getPlaylist()\n");

        $resp = $this->sendCommand(self::CMD_PLLIST);
        $playlist = $this->_parseFileListResponse($resp);

        $this->debug("mpd->getPlaylist() / return " . print_r($playlist)."\n");

        return $playlist;
    }
}