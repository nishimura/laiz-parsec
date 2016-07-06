<?php

namespace Laiz\Parsec\Reply;

use Laiz\Parsec\Reply;
use Laiz\Parsec\State;
use Laiz\Parsec\ParseError;

class Ok implements Reply
{
    private $data;
    private $s;
    private $err;
    public function __construct($data, State $s, ParseError $err)
    {
        $this->data = $data;
        $this->s = $s;
        $this->err = $err;
    }

    public function data(){ return $this->data; }
    public function state(){ return $this->s; }
    public function err(){ return $this->err; }
}

