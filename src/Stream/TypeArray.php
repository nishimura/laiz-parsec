<?php

namespace Laiz\Parsec\Stream;

use Laiz\Parsec\Stream;
use Laiz\Func\Maybe;

class TypeArray implements Stream
{
    public static function uncons($arr)
    {
        if (count($arr) === 0){
            return Maybe\Nothing();
        }

        $as = $arr;
        $a = array_shift($as);
        return Maybe\Just([$a, $as]);
    }
}
