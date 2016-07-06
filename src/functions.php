<?php

namespace Laiz\Parsec;

use Laiz\Func;
use Laiz\Func\Loader;
use function Laiz\Func\f;
use function Laiz\Func\filter;
use function Laiz\Func\foldr;
use function Laiz\Func\cnst;
use function Laiz\Func\Functor\fmap;
use function Laiz\Func\Monad\bind;
use function Laiz\Func\Monoid\mappend;
use Laiz\Func\Maybe;
use function Laiz\Parsec\Stream\uncons;
use function Laiz\Func\Either\Right;
use function Laiz\Func\Either\Left;


const SysUnExpect = 0;
const UnExpect    = 1;
const Expect      = 2;
const Message     = 3;

const CConsumed = 0;
const CEmpty    = 1;


function initialPos($name){
    return new SourcePos($name, 1, 1);
}

function updatePosChar(...$args){
    return f(function(SourcePos $pos, $char){
        if ($char === "\n"){
            return new SourcePos($pos->name(),
                                 $pos->line() + 1,
                                 1);

        }else if ($char === "\t"){
            return new SourcePos($pos->name(),
                                 $pos->line(),
                                 $pos->col() + 8 - (($pos->col() - 1) % 8));

        }else{
            return new SourcePos($pos->name(),
                                 $pos->line(),
                                 $pos->col() + 1);
        }
    }, ...$args);
}


function updatePosString(...$args){
    return f(function(SourcePos $pos, $str){
        $len = strlen($str);
        $name = $pos->name();
        $line = $pos->line();
        $col = $pos->col();
        for ($i = 0; $i < $len; $i++){
            $char = $str[$i];
            if ($char === "\n"){
                $line++;
                $col = 1;
            }else if ($char === "\t"){
                $col = $col + 8 - (($col - 1) % 8);
            }else{
                $col++;
            }
            $pos = updatePosChar($pos, $str[$i]);
        }
        return $pos;
    }, ...$args);
}

function newErrorUnknown(SourcePos $pos){
    return new ParseError($pos, []);
}
function unknownError(State $s){
    return newErrorUnknown($s->pos());
}

function setErrorMessage(Message $msg, ParseError $err)
{
    $msgs = filter(function($a) use ($msg){
        return $a->code() != $msg->code();
    }, $err->msgs());
    array_unshift($msgs, $msg);
    return new ParseError($err->pos(), $msgs);
}
function addErrorMessage(Message $msg, ParseError $err){
    $msgs = $err->msgs();
    array_unshift($msgs, $msg);
    return new ParseError($err->pos(), $msgs);
}
function newErrorMessage(Message $msg, SourcePos $pos){
    return new ParseError($pos, [$msg]);
}

function unexpectError($msg, SourcePos $pos){
    return newErrorMessage(new Message(SysUnExpect, $msg), $pos);
}

function errorIsUnknown(ParseError $e){
    return count($e->msgs()) === 0;
}

function mergeError(...$args)
{
    return f(function(ParseError $e1, ParseError $e2){
        $msgs1 = $e1->msgs();
        $msgs2 = $e2->msgs();
        if (!$msgs2 && $msgs1)
            return $e1;
        else if (!$msgs1 && $msgs2)
            return $e2;

        $pos1 = $e1->pos();
        $pos2 = $e2->pos();
        if ($pos1 == $pos2)
            return new ParseError($pos1, array_merge($msgs1, $msgs2));
        else if ($pos1 > $pos2)
            return $e1;
        else
            return $e2;
    }, ...$args);
}

/**
 * parserMap :: (a -> b) -> Parser s u a -> Parser s u b
 */
function parserMap(...$args)
{
    return f(function($f, $p){
        return new Parser(function($s, $cok, $cerr, $eok, $eerr) use ($f, $p){
            $f2 = $p->unParser();
            $cok2 = f($cok)->compose($f);
            $eok2 = f($eok)->compose($f);
            return $f2($s, $cok2, $cerr, $eok2, $eerr);
        });
    }, ...$args);
}

/**
 * parserReturn :: a -> Parser s u a
 */
function parserReturn(...$args)
{
    return f(function($a){
        return new Parser(function($s, $_, $__, $eok, $___) use ($a){
            return $eok($a, $s, unknownError($s));
        });
    }, ...$args);
}

function parserZero()
{
    return new Parser(function($s, $_, $__, $___, $eerr){
        return $eerr(unknownError($s));
    });
}

function parserPlus(...$args)
{
    return f(function($m, $n){
        $f = function($s, $cok, $cerr, $eok, $eerr) use ($m, $n){
            $meerr = function($err) use ($s, $n, $cok, $cerr, $eok, $eerr){
                $neok = function($y, $s2, $err2) use ($eok, $err){
                    return $eok($y, $s2, mergeError($err, $err2));
                };
                $neerr = function($err2) use ($eerr, $err){
                    return $eerr(mergeError($err, $err2));
                };
                $f = $n->unParser();
                return $f($s, $cok, $cerr, $neok, $neerr);
            };
            $f = $m->unParser();
            return $f($s, $cok, $cerr, $eok, $meerr);
        };

        return new Parser($f);
    }, ...$args);
}


function parserBind(...$args)
{
    return f(function($m, $k){
        $f = function($s, $cok, $cerr, $eok, $eerr) use ($m, $k){
            $mcok = function($x, $s, $err) use ($k, $cok, $cerr){
                $peok = function($x, $s, $err2) use ($cok, $err){
                    return $cok($x, $s, mergeError($err, $err2));
                };
                $peerr = function($err2) use ($cerr, $err){
                    return $cerr(mergeError($err, $err2));
                };
                $f = $k($x)->unParser();
                return $f($s, $cok, $cerr, $peok, $peerr);
            };
            $meok = function($x, $s, $err) use ($k, $cok, $cerr, $eok, $eerr){
                $peok = function($x, $s, $err2) use ($eok, $err){
                    return $eok($x, $s, mergeError($err, $err2));
                };
                $peerr = function($err2) use ($eerr, $err){
                    return $eerr(mergeError($err, $err2));
                };
                $f = $k($x)->unParser();
                return $f($s, $cok, $cerr, $peok, $peerr);
            };

            $f = $m->unParser();
            return $f($s, $mcok, $cerr, $meok, $eerr);
        };

        return new Parser($f);
    }, ...$args);
}

/**
 * (Monoid a) => Parser s u a -> Parser s u a -> Parser s u a
 */
function parserAppend(...$args)
{
    return f(function($m, $n){
        return $m->bind(function($a) use ($n){
            return $n->bind(function($b) use ($a){
                return parserReturn(mappend($a, $b));
            });
        });
    }, ...$args);
}



/**
 * tokens :: (Stream s t, Eq t)
 *        => ([t] -> String)      -- Pretty print a list of tokens
 *        -> (SourcePos -> [t] -> SourcePos)
 *        -> [t]                  -- List of tokens to parse
 *        -> Parser s u [t]
 */
function tokens(...$args){
    return f(function($showTokens, $nextPoss, $tts){
        $maybeTts = uncons($tts);
        if ($maybeTts instanceof Maybe\Nothing){
            return new Parser(function($s, $_, $__, $eok, $___){
                return $eok([], $s, unknownError($s));
            });
        }

        list($tok, $toks) = $maybeTts->fromJust();
        return new Parser(function($s, $cok, $cerr, $eok, $eerr) use ($tts, $tok, $toks, $nextPoss, $showTokens){
            $errEof = function() use ($showTokens, $tts, $s){
                return setErrorMessage(
                    new Message(Expect, $showTokens($tts)),
                    newErrorMessage(new Message(SysUnExpect, ''), $s->pos()));
            };
            $errExpect = f(function($x) use ($showTokens, $tts, $s){
                return setErrorMessage(
                    new Message(Expect, $showTokens($tts)),
                    newErrorMessage(new Message(SysUnExpect, $showTokens([$x])), $s->pos()));
            });
            $ok = function($rs) use ($tts, $cok, $nextPoss, $s){
                $pos2 = $nextPoss($s->pos(), $tts);
                $s2 = new State($rs, $pos2, $s->user());
                return $cok($tts, $s2, newErrorUnknown($pos2));
            };
            $walk = f(function($toks, $rs) use ($ok, $cerr, $errEof, $errExpect, &$walk){
                $m = uncons($toks);
                if ($m instanceof Maybe\Nothing)
                    return $ok($rs);

                list($t, $ts) = $m->fromJust();
                $rm = uncons($rs);
                if ($rm instanceof Maybe\Nothing){
                    return $cerr($errEof());
                }else{
                    list($x, $xs) = $rm->fromJust();
                    if ($t === $x)
                        return $walk($ts, $xs);
                    else
                        return $cerr($errExpect($x));
                }
            });

            $ret = uncons($s->input());
            if ($ret instanceof Maybe\Nothing){
                return $eerr($errEof());
            }else{
                list($x, $xs) = $ret->fromJust();
                if ($tok === $x)
                    return $walk($toks, $xs);
                else
                    return $eerr($errExpect($x));
            }
        });
    }, ...$args);
}

/**
 * runParser :: Parser s u a -> State s u -> Consumed (Reply s u a)
 */
function runParser(...$args){
    return f(function(Parser $p, State $s){
        $f = $p->unParser();

        $cok = function($a, $s2, $err){
            return new Consumed(CConsumed,
                                new Reply\Ok($a, $s2, $err));
        };
        $cerr = function($err){
            return new Consumed(CConsumed,
                                new Reply\Error($err));
        };
        $eok = function($a, $s2, $err){
            return new Consumed(CEmpty,
                                new Reply\Ok($a, $s2, $err));
        };
        $eerr = function($err){
            return new Consumed(CEmpty,
                                new Reply\Error($err));
        };

        return $f($s, $cok, $cerr, $eok, $eerr);
    }, ...$args);
}

/**
 * runPT :: (Stream s t)
 *       => Parser s u a -> u -> SourceName -> s -> Either ParseError a
 */
function runPT(...$args){
    return f(function($p, $u, $name, $s){
        $res = runParser($p, new State($s, initialPos($name), $u));
        $r = $res->data();
        if ($r instanceof Reply\Ok)
            return Right($r->data());
        else
            return Left($r->err());
    }, ...$args);
}

/**
 * parse :: (Stream s t)
 *       => Parser s () a -> SourceName -> s -> Either ParseError a
 */
function parse(...$args){
    return f(function($p){
        return runPT($p, new Func\Unit());
    }, ...$args);
}


//============================================================
// Tokens
//============================================================
/**
 * tokenPrim
 *   :: Stream s t =>
 *      (t -> String)
 *      -> (SourcePos -> t -> s -> SourcePos)
 *      -> (t -> Maybe a)
 *      -> Parser s u a
 */
function tokenPrim(...$args){
    return f(function($showToken, $nextpos, $test){
        return tokenPrimEx($showToken, $nextpos, Maybe\Nothing(), $test);
    }, ...$args);
}

/**
 * tokenPrimEx :: (Stream s t)
 *             => (t -> String)
 *             -> (SourcePos -> t -> s -> SourcePos)
 *             -> Maybe (SourcePos -> t -> s -> u -> u)
 *             -> (t -> Maybe a)     
 *             -> Parser s u a
 */
function tokenPrimEx(...$args){
    return f(function($showToken, $nextpos, $maybeState, $test){
        $f = function($s, $cok, $_, $__, $eerr) use ($showToken, $nextpos, $maybeState, $test){
            $m = uncons($s->input());
            if ($m instanceof Maybe\Nothing)
                return $eerr(unexpectError('', $s->pos()));

            list($c, $cs) = $m->fromJust();
            $ret = $test($c);
            if ($ret instanceof Maybe\Nothing)
                return $eerr(unexpectError($showToken($c), $s->pos()));

            $newpos = $nextpos($s->pos(), $c, $cs);
            $newuser = Maybe\maybe($s->user(), function($nextState) use ($s, $c, $cs){
                return $nextState($s->pos(), $c, $cs, $s->user());
            }, $maybeState);
            $newstate = new State($cs, $newpos, $newuser);

            $x = $ret->fromJust();
            return $cok($x, $newstate, newErrorUnknown($newpos));
        };
        return new Parser($f);
    }, ...$args);
}

//============================================================
// Parsers
//============================================================
/**
 * (<?>) :: (Parser s u a) -> String -> (Parser s u a)
 */
function label(Parser $p, $msg)
{
    return labels($p, [$msg]);
}
Loader::setFunction('label', 'Laiz\Parsec');

/**
 * labels :: Parser s u a -> [String] -> Parser s u a
 */
function labels(Parser $p, $msgs){
    $f = function($s, $cok, $cerr, $eok, $eerr) use ($p, $msgs){
        $setExpectErrors = function($err, $msgs){
            if (count($msgs) === 0)
                return setErrorMessage(new Message(Expect, ''), $err);

            $msg = array_shift($msgs);
            $ret = setErrorMessage(new Message(Expect, $msg), $err);

            return foldr(function($e, $m){
                return addErrorMessage(new Message(Expect, $m), $e);
            }, $ret, $msgs);
        };

        $eok2 = function($x, $s2, $err) use ($eok, $msgs, $setExpectErrors){
            if (!errorIsUnknown($err))
                $err = $setExpectErrors($err, $msgs);
            return $eok($x, $s2, $err);
        };
        $eerr2 = function($err) use ($eerr, $msgs, $setExpectErrors){
            return $eerr($setExpectErrors($err, $msgs));
        };
        $f = $p->unParser();
        return $f($s, $cok, $cerr, $eok2, $eerr2);
    };
    return new Parser($f);
}

/**
 * try :: Parser s u a -> Parser s u a
 */
function tryP(...$args)
{
    return f(function(Parser $p){
        $f = $p->unParser();
        return new Parser(function($s, $cok, $_, $eok, $eerr) use ($f){
            return $f($s, $cok, $eerr, $eok, $eerr);
        });
    }, ...$args);
}

/**
 * unexpected :: (Stream s t) => String -> Parser s u a
 */
function unexpected(...$args)
{
    return f(function($msg){
        $f = function($s, $_, $__, $___, $eerr) use ($msg){
            return $eerr(newErrorMessage(new Message(UnExpect, $msg), $s->pos()));
        };
        return new Parser($f);
    }, ...$args);
}




//============================================================
// State
//============================================================
/**
 * updateParserState :: (State s u -> State s u) -> Parser s u (State s u)
 */
function updateParserState(...$args)
{
    return f(function($f){
        return new Parser(function($s, $_, $__, $eok) use ($f){
            $s2 = $f($s);
            return $eok($s2, $s2, unknownError($s2));
        });
    }, ...$args);
}

/**
 * getParserState :: Parser s u (State s u)
 */
function getParserState()
{
    return updateParserState(Func\id());
}

/**
 * setParserState :: State s u -> Parser s u (State s u)
 */
function setParserState(...$args)
{
    return f(function($s){
        return updateParserState(cnst($s));
    }, ...$args);
}


/**
 * getState :: Parser s u u
 */
function getState()
{
    return fmap(function($s){
        return $s->user();
    }, getParserState());
}

/**
 * putState :: u -> Parser s u ()
 */
function putState(...$args)
{
    return f(function($u){
        return updateParserState(function($s) use ($u){
            return new State($s->input(), $s->pos(), $u);
        })->bind(function($_){
            // (>>)
            return parserReturn(new Func\Unit());
        });
    }, ...$args);
}

/**
 * modifyState :: (u -> u) -> Parser s u ()
 */
function modifyState(...$args)
{
    return f(function($f){
        return updateParserState(function($s) use ($f){
            return new State($s->input(), $s->pos(), $f($s->user()));
        })->bind(function($_){
            // (>>)
            return parserReturn(new Func\Unit());
        });
    }, ...$args);
}


//============================================================
// Function loader
//============================================================
namespace Laiz\Parsec\Char;
function load(){
    require_once __DIR__ . '/char.php';
}

namespace Laiz\Parsec\Combinator;
function load(){
    require_once __DIR__ . '/combinator.php';
}

//============================================================
// Type Classes
//============================================================
namespace Laiz\Parsec\Stream;
use Laiz\Func\Loader;
use function Laiz\Func\f;

Loader::setMethod('uncons', 'Stream', 'Laiz\Parsec');

function uncons(...$args)
{
    return f(function($s){
        return Loader::callInstanceMethod($s, 'uncons', $s);
    }, ...$args);
}

namespace Laiz\Parsec\Show;
use Laiz\Func\Loader;
use function Laiz\Func\f;

Loader::setMethod('show', 'Show', 'Laiz\Parsec');

function show(...$args)
{
    return f(function($a){
        return Loader::callInstanceMethod($a, 'show', $a);
    }, ...$args);
}
