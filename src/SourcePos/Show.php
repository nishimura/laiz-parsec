<?php

namespace Laiz\Parsec\Stream;

class Show implements \Laiz\Parsec\Show
{
    public static function show()
    {
        return '"' . $this->name . '" (line '
                   . $this->line . ', column '
                   . $this->col  . ')';
    }
}
