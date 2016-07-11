<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use function Laiz\Func\f;
use function Laiz\Func\Functor\fmap;
use function Laiz\Func\Applicative\ap;
use function Laiz\Func\Monoid\mempty;
use function Laiz\Func\MonadPlus\mzero;
use function Laiz\Func\Alternative\aempty;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\str;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Either\Right;

class TypeClass extends \PHPUnit_Framework_TestCase
{
    public function testMempty()
    {
        
        $parser = str('ab')->mappend(mempty());
        $ret = parse($parser, "Test", "abcd");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unknown parse error/", show($err));


        $parser = str('ab')->mappend(aempty());
        $ret = parse($parser, "Test", "abcd");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unknown parse error/", show($err));
    }

    public function testFmapAp()
    {
        $first = str('first');
        $parser = fmap(f(function($a, $b){
            return strtoupper($a) . ' ' . ucfirst($b);
        }), $first);
        $parser = ap($parser, str('second'));
        $ret = parse($parser, "Test", "firstsecond");
        $this->assertEquals(Right('FIRST Second'), $ret);

    }
}
