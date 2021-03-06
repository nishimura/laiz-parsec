<?php

namespace Laiz\Parsec\Stream;

use Laiz\Parsec\Stream;
use Laiz\Func\Maybe;

class TypeString implements Stream
{
    public static function uncons($str)
    {
        if (strlen($str) === 0){
            return new Maybe\Nothing();
        }

        if (strlen($str) === 1)
            $as = '';
        else
            $as = substr($str, 1);
        return new Maybe\Just([$str{0}, $as]);
    }
}
