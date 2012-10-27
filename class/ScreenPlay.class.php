<?php

class ScreenPlay extends ScreenAbstract {
    
    protected function encoderConstraints() {
        if ($this->app->encoder_value < 0) {
            $this->app->encoder_value = 0;
            $this->app->serial->sendLine("SET_ENC:0");
        }
        $stations_count = $this->app->player->getStationsCount();
        if ($this->app->encoder_value >= $stations_count) {
            $this->app->encoder_value = $stations_count-1;
            $this->app->serial->sendLine("SET_ENC:" . (string) round($this->app->encoder_value * 4));
        }
    }
    
    public function init() {
        $this->app->encoder_value = $this->app->player->getCurrentIndex();
        $this->encoderConstraints();
        $this->app->serial->sendLine("SET_ENC:" . (string) ($this->app->encoder_value * 4));
        $this->app->serial->sendLine('LED_RED:0');
        $this->app->serial->sendLine('LED_GREEN:1');
    }
    
    public function process() {
        $this->encoderConstraints();

        // set lcd to display the current station
        $station = $this->app->player->getStation($this->app->encoder_value);
        
        switch ($this->app->lcd->getRowsCount()) {
            case 2:
                $this->app->lcd->setLines(
                    array(
                        $station->getName(), 
                        'Playing: ' . ($this->app->encoder_value+1) . ' / ' . $this->app->player->getStationsCount() 
                    )
                );
            break;
            case 4:
                $this->app->lcd->setLines(
                    array(
                        $station->getName(), 
                        $this->app->player->getCurrentTitle(),
                        $this->app->player->getCurrentSong(),
                        'Playing: ' . ($this->app->encoder_value+1) . ' / ' . $this->app->player->getStationsCount() 
                    )
                );
            break;
        }

    }
    
    public function action($time) {
        // change current playing station with 1s delay 
        // (to avoid LCD freezes while changing multiple stations one-by-one using encoder)
        if (!$this->app->player->getIsPlaying() or $this->app->player->getCurrentIndex() != $this->app->encoder_value) {
                if (($time - $this->app->last_updated) >= 0.5) {
                    //$this->app->player->stop();
                    $this->app->player->play($this->app->encoder_value);
                }
        }
    }
    
}