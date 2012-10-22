<?php

class ScreenVolume extends ScreenAbstract {
    
    protected function encoderConstraints() {
        if ($this->app->encoder_value < 0) {
            $this->app->encoder_value = 0;
            $this->app->serial->sendLine("SET_ENC:0");
        }
        if ($this->app->encoder_value >= 100) {
            $this->app->encoder_value = 100;
            $this->app->serial->sendLine("SET_ENC:" . (string) round($this->app->encoder_value * 4));
        }
    }
    
    public function init() {
        $this->app->encoder_value = $this->app->player->getCurrentVolume();
        $this->encoderConstraints();
        $this->app->serial->sendLine("SET_ENC:" . (string) ($this->app->encoder_value * 4));
        if ($this->app->player->getCurrentVolume() != $this->app->encoder_value) {
            $this->app->player->setVolume($this->app->encoder_value);
        }
        $this->app->serial->sendLine('LED_RED:1');
        $this->app->serial->sendLine('LED_GREEN:0');
    }
    
    public function process() {
        $this->encoderConstraints();

        // set lcd to display the current volume
        $this->app->lcd->setBar(
            'Volume: ' . round($this->app->encoder_value) . '%', 
            $this->app->encoder_value
        );
    }
    
    public function action($time) {
        // change current volume with 0,2s delay 
        // (to avoid LCD freezes while changing volume using encoder)
        if ($this->app->player->getCurrentVolume() != $this->app->encoder_value) {
                if (($time - $this->app->last_updated) >= 0.05) {
                    $this->app->player->setVolume($this->app->encoder_value);
                }
        }
    }
    
}