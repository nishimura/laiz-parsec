<?php

namespace Laiz\Parsec;

interface Stream
{
    /**
     * uncons :: s -> Maybe (t,s)
     */
    public static function uncons($s);
}
