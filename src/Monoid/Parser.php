<?php

namespace Laiz\Parsec\Monoid;

use function Laiz\Parsec\parserZero;
use function Laiz\Parsec\parserAppend;

class Parser implements \Laiz\Func\Monoid
{
    public static function mempty()
    {
        return parserZero();
    }

    public static function mappend($a, $b)
    {
        return parserAppend($a, $b);
    }
}
