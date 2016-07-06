<?php

namespace Laiz\Parsec;

use Laiz\Func\CallTrait;

class Parser
{
    use CallTrait;

    private $f;
    public function __construct($f)
    {
        /*
         * data Parser s u a = Parser { unParser :: forall b .
         *                     State s u
         *                     -> (a -> State s u -> ParseError -> b) -- consumed ok
         *                     -> (ParseError -> b)                   -- consumed err
         *                     -> (a -> State s u -> ParseError -> b) -- empty ok
         *                     -> (ParseError -> b)                   -- empty err
         *                     -> b
         *                   }
         */
        $this->f = $f;
    }

    public function unParser()
    {
        return $this->f;
    }
}
