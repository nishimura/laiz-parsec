<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\str;
use function Laiz\Parsec\inFix;
use function Laiz\Parsec\preg;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Either\Right;
use function Laiz\Parsec\initialPos;
use function Laiz\Parsec\updatePosChar;

class ParserCharTest extends \PHPUnit_Framework_TestCase
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

    public function testPos()
    {
        $pos = initialPos('test');
        $this->assertEquals(1, $pos->line());
        $this->assertEquals(1, $pos->col());

        $pos = updatePosChar($pos, 'a');
        $this->assertEquals(1, $pos->line());
        $this->assertEquals(2, $pos->col());

        $pos = updatePosChar($pos, "\n");
        $this->assertEquals(2, $pos->line());
        $this->assertEquals(1, $pos->col());

        $pos = updatePosChar($pos, 'a');
        $this->assertEquals(2, $pos->line());
        $this->assertEquals(2, $pos->col());

        $pos1 = updatePosChar($pos,  "\t");
        $this->assertEquals(2, $pos1->line());
        $this->assertEquals(9, $pos1->col());

        $pos2 = updatePosChar($pos, 'a');
        $this->assertEquals(2, $pos2->line());
        $this->assertEquals(3, $pos2->col());

        $pos3 = updatePosChar($pos2,  "\t");
        $this->assertEquals(2, $pos3->line());
        $this->assertEquals(9, $pos3->col());

        for ($i = 0; $i < 8; $i++)
            $pos = updatePosChar($pos, 'a');
        $this->assertEquals(10, $pos->col());

        $pos = updatePosChar($pos,  "\t");
        $this->assertEquals(17, $pos->col());
    }

    function testStrLft()
    {
        $parser = str("\n");
        $ret = parse($parser, "Test", "\nabc");
        $this->assertEquals(Right("\n"), $ret);

        $parser = str("\n")->mappend(str("\t\nabc"));
        $ret = parse($parser, "Test", "\n\t\nabcabcdef");
        $this->assertEquals(Right("\n\t\nabc"), $ret);
    }

    function testPreg()
    {
        $parser = preg('/^[[:alnum:]]*/');
        $ret = parse($parser, "Test", "abc012@abc");
        $this->assertEquals(Right(["abc012"]), $ret);

        $parser = preg('/^([[:alnum:]]*)@(ab)/');
        $ret = parse($parser, "Test", "abc012@abc");
        $this->assertEquals(Right(['abc012@ab', "abc012", 'ab']), $ret);

        $parser = preg("/^@a/");
        $ret = parse($parser, "Test", "abc012@abc");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertContains("\"Test\" (line 1, column 1):\nunexpected a", show($err));

        $ret = parse($parser, "Test", "@ba");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        // not consumed
        $this->assertContains("\"Test\" (line 1, column 1):\nunexpected @", show($err));
    }
}
