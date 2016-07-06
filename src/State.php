<?php

namespace Laiz\Parsec;

class State
{
    private $input;
    private $pos;
    private $user;

    public function __construct($s, SourcePos $p, $u)
    {
        $this->input = $s;
        $this->pos = $p;
        $this->user = $u;
    }

    public function input(){ return $this->input; }
    public function pos()  { return $this->pos  ; }
    public function user() { return $this->user ; }
}
