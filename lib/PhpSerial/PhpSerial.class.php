<?php

/**
 * Serial port control class
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * Changes added by Rizwan Kassim <rizwank@geekymedia.com> for OSX functionality
 * default serial device for osx devices is /dev/tty.serial for machines with a built in serial device
 *
 * Changes added by Andrey Karpov <andy.karpov@gmail.com> to support php 5.3.
 * Also changed some names, visibility properties and excluded some notices and warnings.
 * All error reporting refactored to throwing Exceptions instead of triggering errors.
 *
 * @author Rémy Sanchez <thenux@gmail.com>
 * @thanks Aurélien Derouineau for finding how to open serial ports with windows
 * @thanks Alec Avedisyan for help and testing with reading
 * @thanks Jim Wright for OSX cleanup/fixes.
 * @copyright under GPL 2 licence
 */
class PhpSerial {

    const SERIAL_DEVICE_CLOSED = 0;
    const SERIAL_DEVICE_SET = 1;
    const SERIAL_DEVICE_OPENED = 2;

    protected $_device = null;
    protected $_windevice = null;
    protected $_dHandle = null;
    protected $_dState = self::SERIAL_DEVICE_CLOSED;
    protected $_buffer = '';
    protected $_os = '';

    /**
     * This var says if buffer should be flushed by sendMessage (true) or manualy (false)
     *
     * @var bool
     */
    public $autoflush = true;

    /**
     * Constructor. Perform some checks about the OS and setserial
     *
     * @throws Exception
     */
    public function __construct() {

        setlocale(LC_ALL, "en_US");

        $sysname = php_uname();

        if (substr($sysname, 0, 5) === "Linux") {
            $this->_os = "linux";
            if($this->_exec("stty --version") === 0) {
                register_shutdown_function(array($this, "close"));
            } else {
                throw new Exception("No stty availible, unable to run.");
            }
        } elseif (substr($sysname, 0, 6) === "Darwin") {
            $this->_os = "osx";
            register_shutdown_function(array($this, "close"));
        } elseif (substr($sysname, 0, 7) === "Windows") {
            $this->_os = "windows";
            register_shutdown_function(array($this, "close"));
        } else {
            throw new Exception("Host OS is neither osx, linux nor windows, unable to run.");
        }
    }

    /**
     * Device set function : used to set the device name/address.
     * -> linux : use the device address, like /dev/ttyS0
     * -> osx : use the device address, like /dev/tty.serial
     * -> windows : use the COMxx device name, like COM1 (can also be used
     *     with linux)
     *
     * @param string $device the name of the device to be used
     * @return bool
     * @throws Exception
     */
    public function setDevice ($device) {
        if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
            if ($this->_os === "linux") {
                if (preg_match("@^COM(\d+):?$@i", $device, $matches)) {
                    $device = "/dev/ttyS" . ($matches[1] - 1);
                }

                if ($this->_exec("stty -F " . $device) === 0) {
                    $this->_device = $device;
                    $this->_dState = self::SERIAL_DEVICE_SET;
                    return true;
                }
            } elseif ($this->_os === "osx") {
                if ($this->_exec("stty -f " . $device) === 0) {
                    $this->_device = $device;
                    $this->_dState = self::SERIAL_DEVICE_SET;
                    return true;
                }
            } elseif ($this->_os === "windows") {
                if (preg_match("@^COM(\d+):?$@i", $device, $matches)
                    and $this->_exec(exec("mode " . $device . " xon=on BAUD=9600")) === 0) {
                    $this->_windevice = "COM" . $matches[1];
                    $this->_device = "\\.\com" . $matches[1];
                    $this->_dState = self::SERIAL_DEVICE_SET;
                    return true;
                }
            }
            throw new Exception("Specified serial port is not valid");
        }
        throw new Exception("You must close your device before to set an other one");
    }

    /**
     * Opens the device for reading and/or writing.
     *
     * @param string $mode Opening mode : same parameter as fopen()
     * @return bool
     * @throws Exception
     */
    public function open ($mode = "r+b")
    {
        if ($this->_dState === self::SERIAL_DEVICE_OPENED) {
            return true;
        }

        if ($this->_dState === self::SERIAL_DEVICE_CLOSED) {
            throw new Exception("The device must be set before to be open");
        }

        if (!preg_match("@^[raw]\+?b?$@", $mode)) {
            throw new Exception("Invalid opening mode : ".$mode.". Use fopen() modes.");
        }

        $this->_dHandle = @fopen($this->_device, $mode);

        if ($this->_dHandle !== false) {
            stream_set_blocking($this->_dHandle, 0);
            $this->_dState = self::SERIAL_DEVICE_OPENED;
            return true;
        }

        $this->_dHandle = null;
        throw new Exception("Unable to open the device");
    }

    /**
     * Closes the device
     *
     * @return bool
     * @throws Exception
     */
    public function close ()
    {
        if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
            return true;
        }

        if (is_resource($this->_dHandle) and fclose($this->_dHandle)) {
            $this->_dHandle = null;
            $this->_dState = self::SERIAL_DEVICE_SET;
            return true;
        }

        throw new Exception("Unable to close the device");
    }

    /**
     * Configure the Baud Rate
     * Possible rates : 110, 150, 300, 600, 1200, 2400, 4800, 9600, 38400,
     * 57600 and 115200.
     *
     * @param int $rate the rate to set the port in
     * @return bool
     * @throws Exception
     */
    public function setBaudRate ($rate)
    {
        if ($this->_dState !== self::SERIAL_DEVICE_SET) {
            throw new Exception("Unable to set the baud rate: the device is either not set or opened");
        }

        $validBauds = array (
            110    => 11,
            150    => 15,
            300    => 30,
            600    => 60,
            1200   => 12,
            2400   => 24,
            4800   => 48,
            9600   => 96,
            19200  => 19,
            38400  => 38400,
            57600  => 57600,
            115200 => 115200
        );

        if (isset($validBauds[$rate])) {

            $ret = null;
            $out = array();

            switch($this->_os) {
                case 'linux':
                    $ret = $this->_exec("stty -F " . $this->_device . " " . (int) $rate, $out);
                break;
                case 'osx':
                    $ret = $this->_exec("stty -f " . $this->_device . " " . (int) $rate, $out);
                break;
                case 'windows':
                    $ret = $this->_exec("mode " . $this->_windevice . " BAUD=" . $validBauds[$rate], $out);
                break;
                default:
                    throw new Exception('Unable to set the baud rate: unsupported OS');
            }

            if ($ret !== 0) {
                throw new Exception("Unable to set baud rate: " . (isset($out[1]) ? $out[1] : 'unknown error'));
            }
        }
    }

    /**
     * Configure parity.
     * Modes : odd, even, none
     *
     * @param string $parity one of the modes
     * @return bool
     * @throws Exception
     */
    public function setParity ($parity)
    {
        if ($this->_dState !== self::SERIAL_DEVICE_SET) {
            throw new Exception("Unable to set parity: the device is either not set or opened");
        }

        $args = array(
            "none" => "-parenb",
            "odd"  => "parenb parodd",
            "even" => "parenb -parodd",
        );

        if (!isset($args[$parity])) {
            throw new Exception("Parity mode not supported");
        }

        $ret = null;
        $out = array();

        switch ($this->_os) {
            case 'linux':
                $ret = $this->_exec("stty -F " . $this->_device . " " . $args[$parity], $out);
            break;
            case 'osx':
                $ret = $this->_exec("stty -f " . $this->_device . " " . $args[$parity], $out);
            break;
            case 'windows':
                $ret = $this->_exec("mode " . $this->_windevice . " PARITY=" . $parity{0}, $out);
            break;
            default:
                throw new Exception('Unable to set parity: unsupported OS');
        }

        if ($ret === 0) {
            return true;
        }

        throw new Exception("Unable to set parity: " . (isset($out[1]) ? $out[1] : 'unknown error'));
    }

    /**
     * Sets the length of a character.
     *
     * @param int $int length of a character (5 <= length <= 8)
     * @return bool
     * @throws Exception
     */
    public function setCharacterLength ($int)
    {
        if ($this->_dState !== self::SERIAL_DEVICE_SET) {
            throw new Exception("Unable to set length of a character: the device is either not set or opened");
        }

        $int = (int) $int;
        if ($int < 5) $int = 5;
        elseif ($int > 8) $int = 8;

        $ret = null;
        $out = array();

        switch ($this->_os) {
            case 'linux':
                $ret = $this->_exec("stty -F " . $this->_device . " cs" . $int, $out);
            break;
            case 'osx':
                $ret = $this->_exec("stty -f " . $this->_device . " cs" . $int, $out);
            break;
            case 'windows':
                $ret = $this->_exec("mode " . $this->_windevice . " DATA=" . $int, $out);
            break;
            default:
                throw new Exception('Unable to set character length: unsupported OS');
        }

        if ($ret === 0) {
            return true;
        }

        throw new Exception("Unable to set character length: " . (isset($out[1]) ? $out[1] : 'unknown error') );
    }

    /**
     * Sets the length of stop bits.
     *
     * @param float $length the length of a stop bit. It must be either 1,
     * 1.5 or 2. 1.5 is not supported under linux and on some computers.
     * @return bool
     * @throws Exception
     */
    public function setStopBits ($length) {
        if ($this->_dState !== self::SERIAL_DEVICE_SET) {
            throw new Exception("Unable to set the length of a stop bit: the device is either not set or opened");
        }

        if ($length != 1 and $length != 2 and $length != 1.5 and !($length == 1.5 and $this->_os === "linux")) {
            throw new Exception("Specified stop bit length is invalid");
        }

        $ret = null;
        $out = array();

        switch($this->_os) {
            case 'linux':
                $ret = $this->_exec("stty -F " . $this->_device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);
            break;
            case 'osx':
                $ret = $this->_exec("stty -f " . $this->_device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);
            break;
            case 'windows':
                $ret = $this->_exec("mode " . $this->_windevice . " STOP=" . $length, $out);
            break;
            default:
                throw new Exception('Unable to set stop bit length: unsupported OS');
        }

        if ($ret === 0) {
            return true;
        }

        throw new Exception("Unable to set stop bit length: " . (isset($out[1]) ? $out[1] : 'unknown error') );
    }

    /**
     * Configures the flow control
     *
     * @param string $mode Set the flow control mode. Availible modes :
     * 	-> "none" : no flow control
     * 	-> "rts/cts" : use RTS/CTS handshaking
     * 	-> "xon/xoff" : use XON/XOFF protocol
     * @return bool
     * @throws Exception
     */
    public function setFlowControl ($mode) {
        if ($this->_dState !== self::SERIAL_DEVICE_SET) {
            throw new Exception("Unable to set flow control mode: the device is either not set or opened");
        }

        $linuxModes = array(
            "none"     => "clocal -crtscts -ixon -ixoff",
            "rts/cts"  => "-clocal crtscts -ixon -ixoff",
            "xon/xoff" => "-clocal -crtscts ixon ixoff"
        );

        $windowsModes = array(
            "none"     => "xon=off octs=off rts=on",
            "rts/cts"  => "xon=off octs=on rts=hs",
            "xon/xoff" => "xon=on octs=off rts=on",
        );

        if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
            throw new Exception("Invalid flow control mode specified");
        }

        $ret = null;
        $out = array();

        switch ($this->_os) {
            case 'linux':
                $ret = $this->_exec("stty -F " . $this->_device . " " . $linuxModes[$mode], $out);
            break;
            case 'osx':
                $ret = $this->_exec("stty -f " . $this->_device . " " . $linuxModes[$mode], $out);
            break;
            case 'windows':
                $ret = $this->_exec("mode " . $this->_windevice . " " . $windowsModes[$mode], $out);
            break;
            default:
                throw new Exception('Unable to set flow control: unsupported OS');
        }

        if ($ret === 0) return true;

        throw new Exception("Unable to set flow control: " . (isset($out[1]) ? $out[1] : 'unknown error') );
    }

    /**
     * Sets a setserial parameter (cf man setserial)
     * NO MORE USEFUL !
     * 	-> No longer supported
     * 	-> Only use it if you need it
     *
     * @param string $param parameter name
     * @param string $arg parameter value
     * @return bool
     * @throws Exception
     */
    public function setSetserialFlag ($param, $arg = "") {

        if (!$this->_ckOpened()) return false;

        $return = exec ("setserial " . $this->_device . " " . $param . " " . $arg . " 2>&1");

        if ($return{0} === "I") {
            throw new Exception("setserial: Invalid flag");
        }
        elseif ($return{0} === "/") {
            throw new Exception("setserial: Error with device file");
        }

        return true;
    }

    /**
     * Sends a string to the device
     *
     * @param string $str string to be sent to the device
     * @param float $waitForReply time to wait for the reply (in seconds)
     */
    public function sendMessage ($str, $waitForReply = 0.1) {
        $this->_buffer .= $str;

        if ($this->autoflush === true) $this->serialFlush();

        usleep((int) ($waitForReply * 1000000));
    }

    /**
     * Reads the port until no new datas are availible, then return the content.
     *
     * @pararm integer $count number of characters to be read (will stop before if less characters are in the buffer)
     * @return mixed
     * @throws Exception
     */
    public function readPort ($count = 0) {

        if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
            throw new Exception("Device must be opened to read it");
        }

        if ($this->_os === "linux" || $this->_os === "osx") {
            // Behavior in OSX isn't to wait for new data to recover, but just grabs what's there!
            // Doesn't always work perfectly for me in OSX
            $content = ""; $i = 0;

            if ($count !== 0) {
                do {
                    if ($i > $count) $content .= fread($this->_dHandle, ($count - $i));
                    else $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        } elseif ($this->_os === "windows") {
            // Windows port reading procedures still buggy
            $content = ""; $i = 0;

            if ($count !== 0) {
                do {
                    if ($i > $count) $content .= fread($this->_dHandle, ($count - $i));
                    else $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            }
            else {
                do {
                    $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        }

        return false;
    }

    /**
     * Flushes the output buffer
     * Renamed from flush for osx compat. issues
     *
     * @return bool
     * @throws Exception
     */
    public function serialFlush ()
    {
        if (!$this->_ckOpened()) return false;

        if (fwrite($this->_dHandle, $this->_buffer) !== false) {
            $this->_buffer = "";
            return true;
        } else {
            $this->_buffer = "";
            throw new Exception("Error while sending message");
        }
    }

    protected function _ckOpened() {
        if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
            throw new Exception("Device must be opened");
        }

        return true;
    }

    protected function _ckClosed() {
        if ($this->_dState !== self::SERIAL_DEVICE_CLOSED) {
            throw new Exception("Device must be closed");
        }

        return true;
    }

    protected function _exec($cmd, &$out = null) {

        $desc = array(
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $retVal = proc_close($proc);

        if (func_num_args() == 2) $out = array($ret, $err);
        return $retVal;
    }

}
