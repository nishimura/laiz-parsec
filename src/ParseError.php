<?php

namespace Laiz\Parsec;

class ParseError
{
    private $pos;
    private $msgs;
    public function __construct($pos, $msgs)
    {
        $this->pos = $pos;
        $this->msgs = $msgs;
    }

    public function pos() { return $this->pos ; }
    public function msgs(){ return $this->msgs; }
}
