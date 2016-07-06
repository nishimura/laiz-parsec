<?php

namespace Laiz\Parsec\Show;

use Laiz\Parsec\Show;

class TypeArray implements Show
{
    public static function show($a)
    {
        if (isset($a[0]) && is_string($a[0]) && strlen($a[0]) === 1)
            return implode('', $a);

        return print_r($a, true);
    }
}
