<?php

namespace Laiz\Parsec\Alternative;

use function Laiz\Parsec\parserZero;
use function Laiz\Parsec\parserPlus;

class Parser implements \Laiz\Func\Alternative
{
    public static function aempty()
    {
        return parserZero();
    }

    public static function aor($a, $b)
    {
        return parserPlus($a, $b);
    }
}
