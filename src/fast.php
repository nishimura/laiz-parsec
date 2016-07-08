<?php

namespace Laiz\Parsec;

use Laiz\Func\Maybe;
use Laiz\Func\Any;
use function Laiz\Func\f;
use function Laiz\Func\foldr;
use function Laiz\Func\Maybe\fromJust;
use function Laiz\Parsec\Stream\uncons;

function _runParser(Parser $p, State $state)
{
    $call = []; // function: [goto label, context]
    $args = []; // function arguments
    $context = []; // function context: function foo() use(...$context)
    $ret = null; // return value

    $call = $p->unParser();
    Stack::push_ret(['_end']);
    $args = [$state,
             ['_call_cok', []], ['_call_cerr', []],
             ['_call_eok', []], ['_call_eerr', []]];
    goto _call;

_call_cok:
    $ret = new Consumed(CConsumed, new Reply\Ok(...$args)); goto _ret;
_call_cerr:
    $ret = new Consumed(CConsumed, new Reply\Error(...$args)); goto _ret;
_call_eok:
    $ret = new Consumed(CEmpty, new Reply\Ok(...$args)); goto _ret;
_call_eerr:
    $ret = new Consumed(CEmpty, new Reply\Error(...$args)); goto _ret;

_call_ret:
    $call = $args[3];
    $args = [$context[0], $args[0], unknownError($args[0])];
    goto _call;

_call_zero:
    $call = $args[4];
    $args = [unknownError($args[0])];
    goto _call;

_call_plus:
    $call = $context[0]->unParser();
    $args = [$args[0], $args[1], $args[2], $args[3],
             ['_call_plus_meerr',
              [$args[0], $context[1], $args[1], $args[2], $args[3], $args[4]]]];
    goto _call;

    _call_plus_meerr:
    $call = $context[1]->unParser();
    $args = [$context[0], $context[2], $context[3],
             ['_call_plus_neok', [$context[4], $args[0]]],
             ['_call_plus_neerr', [$context[5], $args[0]]]];
    goto _call;

    _call_plus_neok:
    $call = $context[0];
    $args = [$args[0], $args[1], mergeError($context[1], $args[2])];
    goto _call;

    _call_plus_neerr:
    $call = $context[0];
    $args = [mergeError($context[1], $args[0])];
    goto _call;


_call_map:
    $call = $context[1]->unParser();
    $args = [$args[0],
             ['_call_map_cok', [$args[1], $context[0]]],
             $args[2],
             ['_call_map_eok', [$args[3], $context[0]]],
             $args[4]];
    goto _call;

    _call_map_cok:
    $call = $context[0];
    $args = [$context[1]($args[0]), $args[1], $args[2]];
    goto _call;
    _call_map_eok:
    $call = $context[0];
    $args = [$context[1]($args[0]), $args[1], $args[2]];
    goto _call;

_call_bind:
    $call = $context[0]->unParser();
    $args = [
        $args[0],
        ['_call_bind_mcok', [$context[0], $context[1], $args[1], $args[2]]],
        $args[2],
        ['_call_bind_meok', [$context[0], $context[1], $args[1], $args[2],
                             $args[3], $args[4]]],
        $args[4]
    ];
    goto _call;

    _call_bind_mcok:
        ;
        $any = $context[1]($args[0]);
        if ($any instanceof Any)
            $any = $any->cast($context[0]);
        $call = $any->unParser();
        $args = [
            $args[1], $context[2], $context[3],
            ['_call_bind_mcok_peok', [$context[2], $args[2]]],
            ['_call_bind_mcok_peerr', [$context[3], $args[2]]]
        ];
        goto _call;

        _call_bind_mcok_peok:
            ;
            $call = $context[0];
            $args = [$args[0], $args[1], mergeError($context[1], $args[2])];
            goto _call;
        _call_bind_mcok_peerr:
            ;
            $call = $context[0];
            $args = [mergeError($context[1], $args[0])];
            goto _call;

    _call_bind_meok:
        $any = $context[1]($args[0]);
        if ($any instanceof Any)
            $any = $any->cast($context[0]);
        $call = $any->unParser();
        $args = [
            $args[1], $context[2], $context[3],
            ['_call_bind_meok_peok', [$context[2], $args[2]]],
            ['_call_bind_meok_peerr', [$context[3], $args[2]]]
        ];
        ;
        goto _call;

        _call_bind_meok_peok:
            ;
            $call = $context[0];
            $args = [$args[0], $args[1], mergeError($context[1], $args[2])];
            goto _call;
        _call_bind_meok_peerr:
            ;
            $call = $context[0];
            $args = [mergeError($context[1], $args[0])];
            goto _call;
    ;



_call_unexpected:
    $call = $args[4];
    $args = [newErrorMessage(new Message(UnExpect, $context[0]),
                             $args[0]->pos())];
    goto _call;


_call_tokenprim:
    $m = uncons($args[0]->input());
    if ($m instanceof Maybe\Nothing){
        $call = $args[4];
        $args = [unexpectError('', $args[0]->pos())];
        goto _call;
    }

    list($c, $cs) = $m->fromJust();
    $r = $context[3]($c);
    if ($r instanceof Maybe\Nothing){
        $call = $args[4];
        $args = [unexpectError($context[0]($c), $args[0]->pos())];
        goto _call;
    }

    $newpos = $context[1]($args[0]->pos(), $c, $cs);
    $newuser = Maybe\maybe($args[0]->user(), function($nextState) use ($args, $c, $cs){
        return $nextState($args[0]->pos(), $c, $cs, $args[0]->user());
    }, $context[2]);
    $newstate = new State($cs, $newpos, $newuser);

    $x = $r->fromJust();
    $call = $args[1];
    $args = [$x, $newstate, newErrorUnknown($newpos)];
    goto _call;


_call_labels:
    $call = $context[0]->unParser();
    $args = [$args[0], $args[1], $args[2],
             ['_call_labels_eok', [$args[3], $context[1]]],
             ['_call_labels_eerr', [$args[4], $context[1]]]];
    goto _call;

    _call_labels_eok:
    if (!errorIsUnknown($args[2]))
        $args[2] = setExpectErrors($args[2], $context[1]);
    $call = $context[0];
    goto _call;

    _call_labels_eerr:
    $call = $context[0];
    $args = [setExpectErrors($args[0], $context[1])];
    goto _call;


_call_tokens:
    $showTokens = $context[4];
    $tts = $context[0];
    $s = $args[0];
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

    $r = uncons($s->input());
    if ($r instanceof Maybe\Nothing){
        $call = $args[4];
        $args = [$errEof()];
        goto _call;
    }else{
        list($x, $xs) = $r->fromJust();
        if ($context[1] === $x){
            $call = ['_call_tokens_walk',
                     [['_call_tokens_ok', [$tts, $args[1], $context[3], $s]],
                      $args[2],
                      $errEof, $errExpect]];
            $args = [$context[2], $xs];
            goto _call;
        }else{
            $call = $args[4];
            $args = [$errExpect($x)];
            goto _call;
        }
    }
    throw new \Exception('Logic Error: _call_tokens');

    _call_tokens_ok:
    $pos2 = $context[2]($context[3]->pos(), $context[0]);
    $s2 = new State($args[0], $pos2, $context[3]->user());
    $call = $context[1];
    $args = [$context[0], $s2, newErrorUnknown($pos2)];
    goto _call;

    _call_tokens_walk:
    $m = uncons($args[0]);
    if ($m instanceof Maybe\Nothing){
        $call = $context[0];
        $args = [$args[1]];
        goto _call;
    }

    list($t, $ts) = $m->fromJust();
    $rm = uncons($args[1]);
    if ($rm instanceof Maybe\Nothing){
        $call = $context[1];
        $args = [$context[2]()];
        goto _call;
    }else{
        list($x, $xs) = $rm->fromJust();
        if ($t === $x){
            $args = [$ts, $xs];
            goto _call;
        }else{
            $call = $context[1];
            $args = [$context[3]()];
            goto _call;
        }
    }
    throw new \Exception('Logic Error: _call_tokens_walk');

    _call_tokens_empty:
    $call = $args[3];
    $args = [[], $args[0], unknownError($args[0])];
    goto _call;


    _call:
    if (!is_array($call)) var_dump($call); // BUG
    list($label, $context) = $call;
    //var_dump([$call, $args]); // test

    if ($label === '_call_ret') goto _call_ret;
    if ($label === '_call_cok') goto _call_cok;
    if ($label === '_call_cerr') goto _call_cerr;
    if ($label === '_call_eok') goto _call_eok;
    if ($label === '_call_eerr') goto _call_eerr;

    if ($label === '_call_map') goto _call_map;
    if ($label === '_call_map_cok') goto _call_map_cok;
    if ($label === '_call_map_eok') goto _call_map_eok;

    if ($label === '_call_bind') goto _call_bind;
    if ($label === '_call_bind_mcok') goto _call_bind_mcok;
    if ($label === '_call_bind_mcok_peok') goto _call_bind_mcok_peok;
    if ($label === '_call_bind_mcok_peerr') goto _call_bind_mcok_peerr;
    if ($label === '_call_bind_meok') goto _call_bind_meok;
    if ($label === '_call_bind_meok_peok') goto _call_bind_meok_peok;
    if ($label === '_call_bind_meok_peerr') goto _call_bind_meok_peerr;

    if ($label === '_call_zero') goto _call_zero;
    if ($label === '_call_plus') goto _call_plus;
    if ($label === '_call_plus_meerr') goto _call_plus_meerr;
    if ($label === '_call_plus_neok') goto _call_plus_neok;
    if ($label === '_call_plus_neerr') goto _call_plus_neerr;

    if ($label === '_call_unexpected') goto _call_unexpected;


    if ($label === '_call_tokenprim') goto _call_tokenprim;
    if ($label === '_call_labels') goto _call_labels;
    if ($label === '_call_labels_eok') goto _call_labels_eok;
    if ($label === '_call_labels_eerr') goto _call_labels_eerr;

    if ($label === '_call_tokens') goto _call_tokens;
    if ($label === '_call_tokens_ok') goto _call_tokens_ok;
    if ($label === '_call_tokens_walk') goto _call_tokens_walk;
    if ($label === '_call_tokens_empty') goto _call_tokens_empty;

    _ret:
    list($label) = Stack::pop_ret();
    if ($label === '_end')
        goto _end;

    _end:

    return $ret;
}

// used in _call_labels
function setExpectErrors(ParseError $err, $msgs){
    if (count($msgs) === 0)
        return setErrorMessage(new Message(Expect, ''), $err);

    $msg = array_shift($msgs);
    $ret = setErrorMessage(new Message(Expect, $msg), $err);

    return foldr(function($e, $m){
        return addErrorMessage(new Message(Expect, $m), $e);
    }, $ret, $msgs);
}

