<?php

namespace Laiz\Parsec\Functor;

use function Laiz\Parsec\parserMap;

class Parser implements \Laiz\Func\Functor
{
    public static function fmap(callable $f, $a)
    {
        return parserMap($f, $a);
    }
}
