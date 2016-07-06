<?php

namespace Laiz\Parsec\Show;

use Laiz\Parsec;
use Laiz\Parsec\Show;
use function Laiz\Parsec\Show\show;
use function Laiz\Parsec\Stream\uncons;
use function Laiz\Func\filter;
use function Laiz\Func\map;
use Laiz\Func\Maybe\Nothing;

class ParseError implements Show
{
    public static function show($a)
    {
        return show($a->pos()) . ':'
            . showErrorMessages('or', 'unknown parse error',
                                'expecting', 'unexpected', 'end of input',
                                errorMessages($a));
    }
}

function showErrorMessages($msgOr, $msgUnknown, $msgExpecting, $msgUnExpected,
                           $msgEndOfInput, $msgs){
    if (!$msgs)
        return $msgUnknown;

    $clean = function($arr){
        $ret = [];
        foreach ($arr as $a){
            $m = uncons($a);
            if ($m instanceof Nothing)
                continue;
            $ret[] = $a;
        }
        $ret = array_unique($ret);
        return $ret;
    };

    $commasOr = function($ms) use ($msgOr){
        $c = count($ms);
        if ($c === 0)
            return '';
        else if ($c === 1)
            return $ms[0];

        $last = array_pop($ms);
        return implode(', ', $ms) . " " . $msgOr . " " . $last;
    };

    $showMany = function($pre, $msgs) use ($clean, $commasOr){
        $ret = $clean(map(function($msg){
            return $msg->msg();
        }, $msgs));
        if (!$ret)
            return '';
        if (!$pre)
            return $commasOr($ret);
        return $pre . " " . $commasOr($ret);
    };

    $sysUnExpect = [];
    $unExpect = [];
    $expect = [];
    $messages = [];
    foreach ($msgs as $msg){
        if ($msg->code() === Parsec\SysUnExpect)
            $sysUnExpect[] = $msg;
        else if ($msg->code() === Parsec\UnExpect)
            $unExpect[] = $msg;
        else if ($msg->code() === Parsec\Expect)
            $expect[] = $msg;
        else
            $messages[] = $msg;
    }
    $showExpect = $showMany($msgExpecting, $expect);
    $showUnExpect = $showMany($msgUnExpected, $unExpect);
    if ($sysUnExpect)
        $firstMsg = $sysUnExpect[0]->msg();
    else
        $firstMsg = '';
    if ($unExpect || !$sysUnExpect)
        $showSysUnExpect = '';
    else if (!$firstMsg)
        $showSysUnExpect = $msgUnExpected . ' ' . $msgEndOfInput;
    else
        $showSysUnExpect = $msgUnExpected . ' ' . $firstMsg;

    $showMessages = $showMany('', $messages);

    $ret = [$showSysUnExpect, $showUnExpect, $showExpect, $showExpect, $showMessages];
    return "\n" . implode("\n", $clean($ret));
}

function errorMessages(\Laiz\Parsec\ParseError $err)
{
    $msgs = $err->msgs();
    sort($msgs);
    return $msgs;
}
