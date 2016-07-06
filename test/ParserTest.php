<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use Laiz\Func\Maybe;
use function Laiz\Func\Maybe\Just;
use function Laiz\Func\Maybe\Nothing;
use function Laiz\Parsec\Stream\uncons;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\Show\show;
use function Laiz\Parsec\char;
use function Laiz\Parsec\str;
use function Laiz\Parsec\satisfy;
use function Laiz\Parsec\label;
use function Laiz\Parsec\labels;
use function Laiz\Parsec\unexpected;
use function Laiz\Parsec\anyToken;
use function Laiz\Parsec\manyTill;
use function Laiz\Func\Either\Right;
use function Laiz\Func\Either\Left;
use function Laiz\Func\Alternative\aor;
use function Laiz\Func\Monoid\mappend;

Parsec\Char\load();

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testUnconsArray()
    {
        $ret = uncons([]);
        $this->assertInstanceof(Maybe\Nothing::class, $ret);

        $ret = uncons([3]);
        $this->assertEquals(Just([3, []]), $ret);

        $ret = uncons([3, 4]);
        $this->assertEquals(Just([3, [4]]), $ret);

        $ret = uncons([3, 4, 5]);
        $this->assertEquals(Just([3, [4, 5]]), $ret);
    }

    public function testUnconsString()
    {
        $ret = uncons('');
        $this->assertInstanceof(Maybe\Nothing::class, $ret);

        $ret = uncons('a');
        $this->assertEquals(Just(['a', '']), $ret);

        $ret = uncons('ab');
        $this->assertEquals(Just(['a', 'b']), $ret);

        $ret = uncons('abc');
        $this->assertEquals(Just(['a', 'bc']), $ret);
    }


    public function testParserInstance()
    {
        $parser = str('ab');
        $this->assertInstanceOf(Parsec\Parser::class, $parser);
    }

    public function testParserSimple()
    {
        $parser = str('ab');

        $ret = parse($parser, "Test", "abc");
        $this->assertEquals(Right('ab'), $ret);

        $parser = str('abc');
        $ret = parse($parser, "Test", "ab");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        
        $this->assertEquals("\"Test\" (line 1, column 1):\nunexpected end of input\nexpecting abc", show($err));
    }

    public function testParserOr()
    {
        $parser = str('ab');
        $parser = aor($parser, str('AB'));

        $ret = parse($parser, "Test", "abAB");
        $this->assertEquals(Right('ab'), $ret);

        $ret = parse($parser, "Test", "ABab");
        $this->assertEquals(Right('AB'), $ret);

        $ret = parse($parser, "Test", "Bab");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/expecting AB or ab/', show($err));

        // CallTrait
        $parser = str('ab');
        $parser = $parser->aor(str('AB'));

        $ret = parse($parser, "Test", "abAB");
        $this->assertEquals(Right('ab'), $ret);
    }

    public function testParserLabel()
    {
        $parser = str('a');
        $ret = parse(label($parser, 'label test'), "Test", "cb");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/expecting label test/', show($err));

        $ret = parse(labels($parser, ['foo', 'bar']), "Test", "cb");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/expecting bar or foo/', show($err));


        // CallTrait
        $parser = str('a');
        $ret = parse($parser->label('label test'), "Test", "cb");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/expecting label test/', show($err));

    }

    public function testUnexpected()
    {
        $parser = str('ab')->const2(unexpected('err'));
        $ret = parse($parser, "Test", "abc");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});

        $this->assertEquals("\"Test\" (line 1, column 3):\nunexpected err", show($err));
    }

    public function testAppend()
    {
        $parser = str('ab');
        $parser = mappend($parser, str('AB'));

        $ret = parse($parser, "Test", "abAB");
        $this->assertEquals(Right('abAB'), $ret);
    }

    function testManyTill()
    {
        $end = str('EOF;');
        $parser = manyTill(anyToken(), $end);

        $ret = parse($parser, "Test", "abcEOF;a");
        $this->assertEquals(Right(['a', 'b', 'c']), $ret);

        $ret = parse($parser, "Test", "EOF;a");
        $this->assertEquals(Right([]), $ret);

        $ret = parse($parser, "Test", "abc");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unexpected end of input/", show($err));
        $this->assertRegExp("/expecting EOF/", show($err));
    }
}
