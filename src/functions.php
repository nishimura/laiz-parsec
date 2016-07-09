<?php

namespace Laiz\Parsec;

use Laiz\Func;
use Laiz\Func\Loader;
use Laiz\Func\Any;
use Laiz\Func\Maybe;
use function Laiz\Func\f;
use function Laiz\Func\filter;
use function Laiz\Func\foldr;
use function Laiz\Func\cnst;
use function Laiz\Func\Functor\fmap;
use function Laiz\Func\Monad\bind;
use function Laiz\Func\Monoid\mappend;
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
    $f = function(SourcePos $pos, $char){
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
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}


function updatePosString(...$args){
    $f = function(SourcePos $pos, $str){
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
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
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

function mergeError(ParseError $e1, ParseError $e2)
{
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
}

/**
 * parserMap :: (a -> b) -> Parser s u a -> Parser s u b
 */
function parserMap(...$args)
{
    $f = function($f, $p){
        return new Parser(['_call_map', [$f, $p]]);
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * parserReturn :: a -> Parser s u a
 */
function parserReturn(...$args)
{
    $f = function($a){
        return new Parser(['_call_ret', [$a]]);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

function parserZero()
{
    return new Parser(['_call_zero', []]);
}

function parserPlus(...$args)
{
    $f = function($m, $n){
        return new Parser(['_call_plus', [$m, $n]]);
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}


function parserBind(...$args)
{
    $f = function($m, $k){
        return new Parser(['_call_bind', [$m, $k]]);
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * (Monoid a) => Parser s u a -> Parser s u a -> Parser s u a
 */
function parserAppend(...$args)
{
    $f = function($m, $n){
        return $m->bind(function($a) use ($n){
            return $n->bind(function($b) use ($a){
                return parserReturn(mappend($a, $b));
            });
        });
    };
    if (count($args) === 2)
        return $f(...$args);
    else
        return f($f, ...$args);
}



/**
 * tokens :: (Stream s t, Eq t)
 *        => ([t] -> String)      -- Pretty print a list of tokens
 *        -> (SourcePos -> [t] -> SourcePos)
 *        -> [t]                  -- List of tokens to parse
 *        -> Parser s u [t]
 */
function tokens(...$args){
    $f = function($showTokens, $nextPoss, $tts){
        $maybeTts = uncons($tts);
        if ($maybeTts instanceof Maybe\Nothing){
            return new Parser(['_call_tokens_empty', []]);
        }

        list($tok, $toks) = $maybeTts->fromJust();
        return new Parser(['_call_tokens', [$tts, $tok, $toks, $nextPoss, $showTokens]]);
    };
    if (count($args) === 3)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * runParser :: Parser s u a -> State s u -> Consumed (Reply s u a)
 */
function runParser(Parser $p, State $s){
    return _runParser($p, $s);
}

/**
 * runPT :: (Stream s t)
 *       => Parser s u a -> u -> SourceName -> s -> Either ParseError a
 */
function runPT($p, $u, $name, $s){
    $res = runParser($p, new State($s, initialPos($name), $u));
    $r = $res->data();
    if ($r instanceof Reply\Ok)
        return Right($r->data());
    else
        return Left($r->err());
}

/**
 * parse :: (Stream s t)
 *       => Parser s () a -> SourceName -> s -> Either ParseError a
 */
function parse(...$args){
    $f = function($p, $name, $s){
        return runPT($p, new Func\Unit(), $name, $s);
    };
    if (count($args) === 3)
        return $f(...$args);
    else
        return f($f, ...$args);
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
    $f = function($showToken, $nextpos, $test){
        return tokenPrimEx($showToken, $nextpos, Maybe\Nothing(), $test);
    };
    if (count($args) === 3)
        return $f(...$args);
    else
        return f($f, ...$args);
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
    // $showToken, $nextpos, $maybeState, $test
    return new Parser(['_call_tokenprim', $args]);
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
    return new Parser(['_call_labels', [$p, $msgs]]);
}

/**
 * try :: Parser s u a -> Parser s u a
 */
function tryP(...$args)
{
    $f = function(Parser $p){
        return new Parser(['_call_try', [$p]]);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

/**
 * unexpected :: (Stream s t) => String -> Parser s u a
 */
function unexpected(...$args)
{
    $f = function($msg){
        return new Parser(['_call_unexpected', [$msg]]);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
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
        return new Parser(['_call_update_parser_state', [$f]]);
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
// Type Classes
//============================================================
namespace Laiz\Parsec\Stream;
use Laiz\Func\Loader;
use function Laiz\Func\f;

Loader::setMethod('uncons', 'Stream', 'Laiz\Parsec');

function uncons(...$args)
{
    $f = function($s){
        return Loader::callInstanceMethod($s, 'uncons', $s);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}

namespace Laiz\Parsec\Show;
use Laiz\Func\Loader;
use function Laiz\Func\f;

Loader::setMethod('show', 'Show', 'Laiz\Parsec');

function show(...$args)
{
    $f = function($a){
        return Loader::callInstanceMethod($a, 'show', $a);
    };
    if (count($args) === 1)
        return $f(...$args);
    else
        return f($f, ...$args);
}
