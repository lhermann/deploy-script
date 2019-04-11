<?php

class Gitlab {
    public $token, $registry, $tag;

    function __construct() {

        $keys = array_keys($_POST);
        if(!(in_array('token', $keys) && in_array('registry', $keys) && in_array('tag', $keys)))
            exit("Error: Values are missing\n");

        $this->token = $_POST["token"];
        $this->registry = $_POST["registry"];
        $this->tag = $_POST["tag"];
    }
}
