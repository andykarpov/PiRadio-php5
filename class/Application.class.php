<?php

class Application {

    /**
     * @var array
     */
    public $cfg;

    /**
     * @var boolean
     */
    protected $running_ok;

    /**
     * @var Serial
     */
    public $serial;

    /**
     * @var Player
     */
    public $player;
    
    /**
     * @var LCD
     */
    public $lcd;
            
    /**
     * @var string
     */
    protected $reading;
    
    public $encoder_value = 0;
    
    protected $button_value = 0;
    
    const APP_STATE_PLAYING = 'playing';
    const APP_STATE_VOLUME = 'volume';
    
    protected $state;
    
    protected $screens = array();
    
    public $last_updated = 0;
    
    public $last_pressed = 0;

    public function __construct() {

        global $cfg;
        $this->cfg = $cfg;
        $this->serial = new Serial($this);
        $this->player = new Player($this);
        $this->lcd = new LCD($this);
        $this->screens[self::APP_STATE_PLAYING] = new ScreenPlay($this);
        $this->screens[self::APP_STATE_VOLUME] = new ScreenVolume($this);

    }

    protected function init() {

        try {
            $this->serial->init();
            $this->player->init();
            $this->lcd->init();
            $this->state = self::APP_STATE_PLAYING;
            $this->screens[$this->state]->init();
        } catch (Exception $e) {
            exit("Init failed");
        }
    }

    public function run() {

        $this->init();

        $this->running_ok = true;

        while ($this->running_ok) {

            $this->running_ok = $this->main(); 

            if (!$this->running_ok) {
                exit(1);
            }
        }
        exit(0);
    }

    protected function main() {

        try {

            $read = $this->serial->read();

            $time = microtime(true);

            $mode_changed = false;
            
            // read encoder and update lcd
            if ($read != $this->reading && !empty($read)) {
                $this->last_updated = $time;
                
                $this->reading = $read;
                $values = explode(":", $read);

                // read encoder value
                if (isset($values[1])) {
                    $this->encoder_value = round($values[1] / 4);
                }
                
                // read button state
                if (isset($values[2])) {
                    $this->button_state = (int) $values[2];
                }

                // change state
                if ($this->button_state && ($time - $this->last_pressed) > 1) {
                    $this->last_pressed = $time;
                    $this->state = ($this->state == self::APP_STATE_PLAYING) ? self::APP_STATE_VOLUME : self::APP_STATE_PLAYING;
                    $mode_changed = true;
                }

                $screen = $this->screens[$this->state];
                
                if ($mode_changed) {
                    $screen->init();
                }
                
                $screen->process();
                
                if ($this->lcd->getNeedUpdate()) {
                    $this->lcd->update();
                }
            }

            if (!$mode_changed) {
                $this->screens[$this->state]->action($time);
            }
            
            return true;

        } catch (Exception $e) {
            return false;
        }
    }
}
