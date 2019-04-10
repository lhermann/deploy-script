<?php

class Gitlab {
    public $token, $registry, $tag;

    function __construct() {
        $this->token = $_POST["token"];
        $this->registry = $_POST["registry"];
        $this->tag = $_POST["tag"];
    }
}
