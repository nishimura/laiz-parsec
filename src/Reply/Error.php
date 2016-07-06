<?php

namespace Laiz\Parsec\Reply;

use Laiz\Parsec\Reply;
use Laiz\Parsec\ParseError;

class Error implements Reply
{
    private $err;
    public function __construct(ParseError $err)
    {
        $this->err = $err;
    }

    public function err(){ return $this->err; }
}

