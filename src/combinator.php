<?php

namespace Laiz\Parsec;

use Laiz\Func;
use function Laiz\Func\f;
use function Laiz\Func\colonr;
use function Laiz\Func\Functor\fmap;
use function Laiz\Func\Monad\bind;
use function Laiz\Func\Alternative\aor;
use function Laiz\parsec\Show\show;

/**
 * PHP array ['a', 'b' 'c'] to PHP string "abc"
 *
 * flat : ParsecT s u m [Char] -> ParsecT s u m String
 * flat : ParsecT s u m [(PHP string)] -> ParsecT s u m (PHP string)
 */
function flat(...$args)
{
    $f = function(Parser $p){
        return $p->bind(function($xs){
            return parserReturn(implode('', $xs));
        });
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

function manyErr(){
    return "Laiz\Parsec\many: combinator 'many' is applied to a parser that accepts an empty string.";
};
/**
 * manyAccum :: (a -> [a] -> [a])
 *           -> Parser s u a
 *           -> Parser s u [a]
 */
function manyAccum($acc, Parser $p)
{
    return new Parser(['_call_many_accum', [$acc, $p]]);
}

/**
 * many :: Parser s u a -> Parser s u [a]
 */
function many(...$args)
{
    $f = function(Parser $p){
        return manyAccum(colonr(), $p);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * many1 :: (Stream s t) => Parser s u a -> Parser s u [a]
 */
function many1(...$args)
{
    $f = function(Parser $p){
        return $p->bind(function($a) use ($p){
            return many($p)->bind(function($as) use ($a){
                array_unshift($as, $a);
                return parserReturn($as);
            });
        });
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * skipMany :: Parser s u a -> Parser s u ()
 */
function skipMany(...$args)
{
    $f = function(Parser $p){
        return manyAccum(function($_, $__){
            return [];
        }, $p)->bind(function($_){
            return parserReturn(new Func\Unit());
        });
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * skipMany1 :: (Stream s t) => Parser s u a -> Parser s u ()
 */
function skipMany1(...$args)
{
    $f = function(Parser $p){
        return $p->bind(function($_) use ($p){
            return skipMany($p);
        });
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * option :: (Stream s t) => a -> Parser s u a -> Parser s u a
 */
function option(...$args)
{
    $f = function($a, Parser $p){
        return $p->aor(parserReturn($a));
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * optional :: (Stream s t) => Parser s u a -> Parser s u ()
 */
function optional(...$args)
{
    $f = function(Parser $p){
        return $p->bind(function($_){
            return parserReturn(new Func\Unit());
        })->aor(parserReturn(new Func\Unit()));
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}


/**
 * choice :: [Parser s u a] -> Parser s u a
 */
function choice(...$args)
{
    $f = function($ps){
        // foldr
        $ret = parserZero();
        for ($i = count($ps) - 1; $i >= 0; $i--){
            $ret = aor($ps[$i], $ret);
        }
        return $ret;
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}


/**
 * between :: (Stream s t) => Parser s u open -> Parser s u close
 *            -> Parser s u a -> Parser s u a
 */
function between(...$args)
{
    $f = function(Parser $open, Parser $close, Parser $p){
        return $open->bind(function($_) use ($close, $p){
            return $p->bind(function($x) use ($close){
                return $close->bind(function($_) use ($x){
                    return parserReturn($x);
                });
            });
        });
    };
    if (count($args) === 3)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * sepBy1 :: Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function sepBy1(...$args)
{
    $f = function(Parser $p, Parser $sep){
        return $p->bind(function($x) use ($sep, $p){
            return many($sep->bind(function($_) use($p){ return $p; }))
                ->bind(function($xs) use ($x){
                    array_unshift($xs, $x);
                    return parserReturn($xs);
                })->aor(parserReturn([$x]));
        });
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * sepBy :: Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function sepBy(...$args)
{
    $f = function(Parser $p, Parser $sep){
        return sepBy1($p, $sep)->aor(parserReturn([]));
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * sepEndBy1 :: Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function sepEndBy1(...$args)
{
    $f = function(Parser $p, Parser $sep){
        return $p->bind(function($x) use ($p, $sep){
            return $sep->bind(function($_) use ($p, $sep, $x){
                return sepEndBy($p, $sep)->bind(function($xs) use ($x){
                    array_unshift($xs, $x);
                    return parserReturn($xs);
                });
            })->aor(parserReturn([$x]));
        });
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}
/**
 * sepEndBy :: Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function sepEndBy(...$args)
{
    $f = function(Parser $p, Parser $sep){
        return sepEndBy1($p, $sep)->aor(parserReturn([]));
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * endBy1 :: Stream s t => Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function endBy1(...$args)
{
    $f = function(Parser $p, Parser $sep){
        $p2 = $p->bind(function($x) use ($sep){
            return $sep->bind(function($_) use ($x){
                return parserReturn($x);
            });
        });
        return many1($p2);
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}
/**
 * endBy :: Parser s u a -> Parser s u sep -> Parser s u [a]
 */
function endBy(...$args)
{
    $f = function(Parser $p, Parser $sep){
        $p2 = $p->bind(function($x) use ($sep){
            return $sep->bind(function($_) use ($x){
                return parserReturn($x);
            });
        });
        return many($p2);
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * anyToken :: (Stream s t, Show t) => Parser s u t
 */
function anyToken()
{
    return tokenPrim(show(), function($pos, $_tok, $_toks){
        return $pos;
    }, Func\Maybe\Just());
}

/**
 * notFollowedBy :: (Show a, Stream s t) => Parser s u a -> Parser s u ()
 */
function notFollowedBy(...$args)
{
    $f = function(Parser $p){
        $p2 = tryP($p)->bind(function($c){
            return unexpected(show($c));
        })->aor(parserReturn(new Func\Unit()));
        return tryP($p2);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * eof :: (Stream s t, Show t) => Parser s u ()
 */
function eof()
{
    return notFollowedBy(anyToken())->label('end of input');
}


/**
 * manyTill :: (Stream s t) => Parser s u a -> Parser s u end -> Parser s u [a]
 */
function manyTill(...$args)
{
    $f = function(Parser $p, Parser $end){
        $scan = function() use (&$scan, $p, $end){
            return $end->bind(function($_){
                return parserReturn([]);
            })->aor($p->bind(function($x) use (&$scan){
                return $scan()->bind(function($xs) use ($x){
                    array_unshift($xs, $x);
                    return parserReturn($xs);
                });
            }));
        };
        return $scan();
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}
