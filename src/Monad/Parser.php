<?php

namespace Laiz\Parsec\Monad;

use Laiz\Parsec\Applicative;
use function Laiz\Parsec\parserBind;

class Parser extends Applicative\Parser implements \Laiz\Func\Monad
{
    public static function ret($a)
    {
        return parent::pure($a);
    }

    public static function bind($m, callable $f)
    {
        return parserBind($m, $f);
    }
}
