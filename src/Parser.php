<?php

namespace Laiz\Parsec;

use Laiz\Func\CallTrait;
use Laiz\Parsec;
use Laiz\Func\Any;

class Parser
{
    use CallTrait;

    private $f;
    public function __construct($f)
    {
        /*
         * [call_args, ret_args]
         */
        /*
         * call_args => [arg1, arg2, ..., call_label]
         * ret_args => [storeName1 => storeValue1, ..., ret_label]
         */

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

    /**
     * Override Applicative
     */
    public function const1($b)
    {
        if ($b instanceof Any)
            $b = $b->cast($this);
        return const1($this, $b);
    }

    /**
     * Override Applicative
     */
    public function const2($b)
    {
        if ($b instanceof Any)
            $b = $b->cast($this);
        return const2($this, $b);
    }

    public function aor($b)
    {
        if ($b instanceof Any)
            $b = $b->cast($this);
        return Alternative\Parser::aor($this, $b);
    }
    public function ap($b)
    {
        if ($b instanceof Any)
            $b = $b->cast($this);
        return Applicative\Parser::ap($this, $b);
    }
    public function bind($f)
    {
        return Monad\Parser::bind($this, $f);
    }
}
