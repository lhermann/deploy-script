<?php

class Request {
    private static $active = true;

    public static function is_active() {
        return self::$active;
    }

    public static function start() {
        ob_start();
        ob_implicit_flush();
    }

    public static function flush() {
        ob_flush();
    }

    public static function end() {
        self::$active = false;
        if (is_callable('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            header('Content-Encoding: none');
            header('Content-Length: '.ob_get_length());
            header('Connection: close');
            ob_end_flush();
            flush();
        }
    }
}
