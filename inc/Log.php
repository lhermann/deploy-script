<?php

class Log {
    private static $file = "deploy.log";
    public static $print = true;

    public static function filename() {
        return dirname(__DIR__) . '/logs/' . self::$file;
    }

    public static function set_file($file) {
        self::$file = $file;
    }

    public static function disable_print() {
        self::$print = false;
    }

    // Create logfile or empty existing one
    public static function setup_logfile() {
        file_put_contents(self::filename(), "");
    }

    public static function write($string = "", $print = true, $log = true) {
        $print_str = is_string($string) ? $string : print_r($string, true);
        $print_str = trim($print_str);

        if($print && self::$print)
            print($print_str . "\n");

        if($log) {
            file_put_contents(
                self::filename(),
                sprintf( "[%s] %s\n",
                        date("Y-m-d H:i:s"),
                        $print_str
                ),
                FILE_APPEND
            );
        }
    }
}
