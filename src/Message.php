<?php

namespace Laiz\Parsec;

class Message
{
    private $code;
    private $msg;
    public function __construct($code, $msg)
    {
        $this->code = $code;
        $this->msg = $msg;
    }

    public function code(){ return $this->code; }
    public function msg() { return $this->msg ; }
}
