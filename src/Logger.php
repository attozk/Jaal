<?php

namespace Hathoora\Jaal;

use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface
{
    /**
     * @var Logger
     */
    protected static $instance;

    /**
     * Minimum debug level
     */
    private $levelWeights
        = [
            'DEBUG'     => 0,
            'INFO'      => 1,
            'NOTICE'    => 2,
            'WARNING'   => 3,
            'ERROR'     => 4,
            'CRITICAL'  => 5,
            'ALERT'     => 6,
            'EMERGENCY' => 7
        ];

    private $levelWeight = 4;
    private $colors;

    private $arrColorsFG
        = [
            'black'       => '0;30',
            'darkGray'    => '1;30',
            'blue'        => '0;34',
            'lightBlue'   => '1;34',
            'green'       => '0;32',
            'lightGreen'  => '1;32',
            'cyan'        => '0;36',
            'lightCyan'   => '1;36',
            'red'         => '0;31',
            'lightRed'    => '1;31',
            'purple'      => '0;35',
            'lightPurple' => '1;35',
            'brown'       => '0;33',
            'yellow'      => '1;33',
            'lightGray'   => '0;37',
            'white'       => '1;37'
        ];

    private $arrColorsBG
        = [
            'black'      => '40',
            'red'        => '41',
            'green'      => '42',
            'yellow'     => '43',
            'blue'       => '44',
            'magenta'    => '45',
            'cyan'       => '46',
            'light_gray' => '47'
        ];

    private function __construct()
    {
        $level = Jaal::getInstance()->config->get('jaal.debug.level');

        if (is_numeric($level)) {
            $this->levelWeight = $level;
        } else if (($level = strtoupper($level)) && isset($this->levelWeights[$level])) {
            $this->levelWeight = $this->levelWeights[$level];
        }

        $this->colors = Jaal::getInstance()->config->get('jaal.debug.colors');
    }

    // Returns colored string
    public function color($string, $foreground_color = NULL, $background_color = NULL)
    {
        if (!$this->colors) {
            return $string;
        }

        $colored_string = "";

        // Check if given foreground color found
        if (isset($this->arrColorsFG[$foreground_color])) {
            $colored_string .= "\033[" . $this->arrColorsFG[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->arrColorsBG[$background_color])) {
            $colored_string .= "\033[" . $this->arrColorsBG[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .= $string . "\033[0m";

        return $colored_string;
    }

    /**
     * System is unusable.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = [])
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /**
     * Action must be taken immediately.
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = [])
    {
        $this->log('ALERT', $message, $context);
    }

    /**
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = [])
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = [])
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Normal but significant events.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function notice($message, array $context = [])
    {
        $this->log('NOTICE', $message, $context);
    }

    /**
     * Interesting events.
     * Example: User logs in, SQL logs.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Detailed debug information.

     *
*@param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function log($level, $message, array $context = [])
    {
        if ((is_numeric($level) && $level >= $this->levelWeight) ||
            (isset($this->levelWeights[$level]) && $this->levelWeights[$level] >= $this->levelWeight)
        ) {
            echo '[' . $level . '] ' . $message . "\n";
        }
    }

    /**
     * Call this method to get singleton
     *
     * @return Logger
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }
}