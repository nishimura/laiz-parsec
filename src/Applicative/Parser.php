<?php

namespace Laiz\Parsec\Applicative;

use function Laiz\Func\Monad\bind;
use Laiz\Parsec\Functor;
use function Laiz\Parsec\parserReturn;
use function Laiz\Parsec\parserPlus;

class Parser extends Functor\Parser implements \Laiz\Func\Applicative
{
    public static function pure($a)
    {
        return parserReturn($a);
    }

    public static function ap($mf, $ma)
    {
        return bind($mf, function($f) use ($ma){
            return bind($ma, function($a) use ($f){
                return self::pure($f($a));
            });
        });
    }
}
