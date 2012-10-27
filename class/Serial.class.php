<?php

class Serial {

    /**
     * Application instance
     * @var ApplicationCli
     */
    protected $app;
    
    /**
     * Low level serial port implementation
     * @var PhpSerial
     */
    protected $php_serial;
    
    /**
     * Constructor
     * 
     * @param ApplicationCli $app
     */
    public function __construct(ApplicationCli &$app) {
        $this->app = $app;
        $this->php_serial = new PhpSerial();
    }
    
    /**
     * Init routine
     * 
     */
    public function init() {
        
        $env = $this->app->cfg['environment'][$this->app->cfg['env']];

        // init PhpSerial
        $this->php_serial->setDevice($env['device']);
        $this->php_serial->setBaudRate($env['baud_rate']);
        $this->php_serial->setParity($env['parity']);
        $this->php_serial->setCharacterLength($env['char_length']);
        $this->php_serial->setStopBits($env['stop_bits']);
        $this->php_serial->setFlowControl($env['flow_control']);

        $this->php_serial->open();

        // let's arduino to boot wait
        sleep(2);
    }
    
    /**
     * Send line of text to the serial port
     * 
     * @param string $msg
     */
    public function sendLine($msg) {
        $this->php_serial->sendMessage($msg . "\n");
    }
    
    /**
     * Send a command to the serial port and
     * read response (a line of text) from the serial port
     * 
     * @param string $cmd
     * @return string
     */
    public function read($cmd = 'READ:') {
        $this->sendLine($cmd);
        return $this->php_serial->readPort();
    }
    
    /**
     * Destructor
     * 
     */
    public function __destruct() {
         $this->php_serial->close();
    }
    
}