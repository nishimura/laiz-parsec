<?php

namespace Laiz\Parsec\Show;

use Laiz\Parsec\Show;

class SourcePos implements Show
{
    public static function show($a)
    {
        $ret = '';
        if ($a->name())
            $ret .= '"' . $a->name() . '" ';

        $ret .= '(line ' . $a->line() . ', column '
             . $a->col() . ')';
        return $ret;
    }
}
