<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\str;
use function Laiz\Parsec\inFix;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Either\Right;

class TextTest extends \PHPUnit_Framework_TestCase
{
    public function testParserInFix()
    {
        $parser = inFix(str('ab'));

        $ret = parse($parser, "Test", "cccab");
        $this->assertEquals(Right('ab'), $ret);

        $ret = parse($parser, "Test", "cccaccb");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertEquals("\"Test\" (line 1, column 8):\nunexpected end of input\nexpecting ab", show($err));
    }
}
