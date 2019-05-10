<?php

class Gitlab {
    public $token, $registry, $tag;

    function __construct() {

        $keys = array_keys($_POST);

        if(!in_array('registry', $keys)) exit("Value is missing: 'registry'\n");
        if(!in_array('tag', $keys)) exit("Value is missing: 'tag'\n");

        $this->registry = $_POST["registry"];
        $this->tag = $_POST["tag"];
    }
}
