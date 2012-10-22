<?php

class LCD {

    protected $app = null;
    
    protected $rows_count = 2;
    protected $chars_count = 16;
    
    protected $lines = array();
    protected $bar_text = '';
    protected $bar_value = 0;
    protected $need_update = false;
    protected $mode = 'text';
    protected $modes = array('text', 'bar');    
    
    public function __construct(Application &$app) {
        $this->app = $app;
    }
    
    public function init() {
        $this->setMode('text');
        for ($i=0; $i<$this->rows_count; $i++) {
            $this->setLine($i, '');
        }
    }
    
    public function setMode($mode) {
        if (!in_array($mode, $this->modes)) return;
        if ($this->mode != $mode) {
            $this->setNeedUpdate(true);
        }
        $this->mode = $mode;
    }
    
    public function getMode() {
        return $this->mode;
    }
    
    public function setLines($lines = array()) {
        $this->setMode('text');
        for ($i=0; $i<$this->rows_count; $i++) {
            $this->setLine($i, (!empty($lines[$i])) ? $lines[$i] : '');
        }
    }
    
    public function setLine($index, $text) {
        $this->setMode('text');
        if ($index >= 0 && $index < $this->rows_count) {
            if (!isset($this->lines[$index]) or $this->lines[$index] != $text) {
                $this->setNeedUpdate(true);
            }
            $this->lines[$index] = (string) $text;
        }
    }
    
    public function setBar($text, $value) {
        $this->setMode('bar');
        if ($text != $this->bar_text or $value != $this->bar_value) {
            $this->setNeedUpdate(true);
        }
        $this->bar_text = $text;
        $this->bar_value = $value;
    }
    
    public function getNeedUpdate() {
        return $this->need_update;
    }
    
    public function setNeedUpdate($need) {
        $this->need_update = (boolean) $need;
    }
    
    protected function adjustText($text) {
        return str_pad(substr($text, 0, $this->chars_count), $this->chars_count, " ");
    }
    
    public function update() {
        
        switch ($this->mode) {
            case 'text':
                for ($i=0; $i<$this->rows_count; $i++) {
                    $this->app->serial->sendLine(
                        sprintf(
                            'TEXT%d:%s',
                            $i+1,
                            $this->adjustText($this->lines[$i])
                        )
                    );
                }
            break;
            case 'bar':
                $this->app->serial->sendLine(
                    sprintf(
                        'TEXT%d:%s',
                        $this->rows_count-1,
                        $this->adjustText($this->bar_text)
                    )
                );
                $this->app->serial->sendLine(
                    sprintf(
                        'BAR%d:%d',
                        $this->rows_count,
                        $this->bar_value
                    )
                );
            break;
        }
        
        $this->setNeedUpdate(false);
    }
}