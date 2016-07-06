<?php

namespace Laiz\Parsec;

class Consumed
{
    private $code;
    private $data;
    public function __construct($code, $data)
    {
        $this->code = $code;
        $this->data = $data;
    }

    public function code(){ return $this->code; }
    public function data(){ return $this->data; }
}
