<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use Laiz\Func\Maybe;
use function Laiz\Func\Either\Right;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\runPT;
use function Laiz\Parsec\str;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Alternative\aor;
use function Laiz\Func\Applicative\ap;
use function Laiz\Func\Monad\bind;
use function Laiz\Func\cnst;
use function Laiz\Parsec\getState;
use function Laiz\Parsec\putState;
use function Laiz\Parsec\modifyState;

class ParserStateTest extends \PHPUnit_Framework_TestCase
{
    public function testGetState()
    {
        $parser = str('ab');

        $state = ['state1' => 3, 'state2' => 2];

        $ret = runPT($parser, $state, "Test", "abc");
        $this->assertEquals(Right('ab'), $ret);

        $p2 = $parser->bind(cnst(getState()));
        $ret = runPT($p2, $state, "Test", "abc");
        $s = null;
        $ret->either(function(){}, function($a) use (&$s){ $s = $a; });
        $this->assertEquals($state, $s);

        $p3 = $parser->const2(getState());
        $ret = runPT($p3, $state, "Test", "abc");
        $s = null;
        $ret->either(function(){}, function($a) use (&$s){ $s = $a; });
        $this->assertEquals($state, $s);
    }

    public function testSetState()
    {
        $state = ['state1' => 3, 'state2' => 2];
        $parser = putState($state)->const2(str('ab'))->const2(getState());
        $ret = parse($parser, "Test", "abc");

        $s = null;
        $ret->either(function(){}, function($a) use (&$s){ $s = $a; });
        $this->assertEquals($state, $s);
    }


    public function testModifyState()
    {
        $state = ['state1' => 3, 'state2' => 2];
        $parser = str('ab')->const1(modifyState(function($u){
            $u['state2'] = 4;
            $u['state3'] = 1;
            return $u;
        }))->const2(getState());
        $ret = runPT($parser, $state, "Test", "abc");

        $s = null;
        $ret->either(function(){}, function($a) use (&$s){ $s = $a; });
        $this->assertEquals(['state1' => 3,
                             'state2' => 4,
                             'state3' => 1], $s);
    }
}
