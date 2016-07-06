<?php

namespace Laiz\Parsec;

class SourcePos
{
    private $name;
    private $line;
    private $col;
    public function __construct($name, $line, $col)
    {
        $this->name = $name;
        $this->line = $line;
        $this->col = $col;
    }

    public function name(){ return $this->name; }
    public function line(){ return $this->line; }
    public function col() { return $this->col ; }

}
