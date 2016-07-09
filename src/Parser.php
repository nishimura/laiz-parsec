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
        return const1($this, $b);
    }

    /**
     * Override Applicative
     */
    public function const2($b)
    {
        return const2($this, $b);
    }
}
