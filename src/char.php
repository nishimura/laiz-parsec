<?php

namespace Laiz\Parsec;

use function Laiz\Func\f;
use function Laiz\Func\cnst;
use function Laiz\Func\Alternative\aor;
use function Laiz\Func\Monad\bind;
use Laiz\Func\Maybe;
use function Laiz\parsec\Show\show;

/**
 * satisfy :: (Stream s Char) => (Char -> Bool) -> Parser s u Char
 */
function satisfy(...$args){
    $f = function($f){
        return tokenPrim(function($c){
            return show([$c]);
        }, function($pos, $c, $_cs){
            return updatePosChar($pos, $c);
        }, function($c) use ($f){
            if ($f($c))
                return new Maybe\Just($c);
            else
                return new Maybe\Nothing();
        });
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * space :: (Stream s Char) => Parser s u Char
 */
function space(){
    return label(satisfy(function($s){
        return $s === ' ' || $s === "\t" || $s === "\n" || $s === "\r";
    }), 'space');
}

/**
 * char :: (Stream s Char) => Char -> Parser s u Char
 */
function char($c){
    return label(satisfy(function($a) use ($c){
        return $c === $a;
    }), show([$c]));
}

/**
 * anyChar :: (Stream s Char) => Parser s u Char
 */
function anyChar(){
    return satisfy(cnst(true));
}

/**
 * inFix :: Stream s Char => Parser s u a -> Parser s u a
 */
function inFix(...$args){
    $f = function(Parser $p){
        return aor(tryP($p), bind(anyChar(), function($_) use ($p){
            return inFix($p);
        }));
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * string :: (Stream s Char) => String -> Parser s u String
 */
function str($s){
    return tokens(
        function($a){ return show($a); },
        function($pos, $str){ return updatePosString($pos, $str); },
        $s);
}

/**
 * String -> Parser String u [String]
 * NOT USE Stream, uncons
 */
function preg($pattern){
    return new Parser(['_call_preg', [$pattern]]);
}
