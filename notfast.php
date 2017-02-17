<?php

// moved to fast.php
function preg($pattern){
    return new Parser(function($s, $cok, $_, $__, $eerr) use ($pattern){
        $str = $s->input();
        if ($str === '')
            return $eerr(unexpectError('', $s->pos()));

        if (!preg_match($pattern, $str, $matches))
            return $eerr(unexpectError($str[0], $s->pos()));

        $newpos = updatePosString($s->pos(), $matches[0]);
        $newstr = substr($str, strlen($matches[0]));
        $newstate = new State($newstr, $newpos, $s->user());
        return $cok($matches, $newstate, newErrorUnknown($newpos));
    });
}
