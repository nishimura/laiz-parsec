<?php

namespace Laiz\Test\Parsec;

use Laiz\Func;
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
use function Laiz\Parsec\many;
use function Laiz\Parsec\many1;
use function Laiz\Parsec\manyTill;
use function Laiz\Parsec\anyToken;
use function Laiz\Parsec\skipMany;
use function Laiz\Parsec\skipMany1;
use function Laiz\Parsec\tryP;
use function Laiz\Parsec\option;
use function Laiz\Parsec\optional;
use function Laiz\Parsec\choice;
use function Laiz\Parsec\between;
use function Laiz\Parsec\flat;
use function Laiz\Parsec\sepBy;
use function Laiz\Parsec\sepBy1;
use function Laiz\Parsec\sepEndBy;
use function Laiz\Parsec\sepEndBy1;
use function Laiz\Parsec\endBy;
use function Laiz\Parsec\endBy1;
use function Laiz\Parsec\eof;
use function Laiz\Func\Either\Right;
use function Laiz\Func\Either\Left;
use function Laiz\Func\Alternative\aor;
use function Laiz\Func\Functor\fmap;

class ParserCombinatorTest extends \PHPUnit_Framework_TestCase
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

    public function testParserMany()
    {
        $parser = str('ab');

        $ret = parse(many($parser), "Test", "abababc");
        $this->assertEquals(Right(['ab', 'ab', 'ab']), $ret);

        $parser = str('abc');
        $manyP = many($parser);
        $ret = parse($manyP, "Test", "abcde");
        $this->assertEquals(Right(['abc']), $ret);


        $ret = parse($manyP, "Test", "abcabe");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected e/', show($err));


        $parser2 = tryP(str('abc'));
        $manyP2 = many($parser2);
        $ret = parse($manyP2, "Test", "abcabe");
        $this->assertEquals(Right(['abc']), $ret);

        $ret = parse(tryP($manyP), "Test", "abcabe");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected e/', show($err));
    }

    public function testManyEmpty()
    {
        $parser = str('abc');
        $manyP = many($parser);
        $ret = parse($manyP, "Test", "def");
        $this->assertEquals(Right([]), $ret);


        $parser = str('abc');
        $manyP = many1($parser);
        $ret = parse($manyP, "Test", "abc");
        $this->assertEquals(Right(['abc']), $ret);


        $parser = str('abc');
        $manyP = many1($parser);
        $ret = parse($manyP, "Test", "def");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected d/', show($err));
    }

    public function testSkipMany()
    {
        $parser = str('abc');
        $manyP = skipMany($parser);
        $ret = parse($manyP, "Test", "abcabcd");
        $this->assertEquals(Right(new \Laiz\Func\Unit()), $ret);

        $ret = parse($manyP, "Test", "def");
        $this->assertEquals(Right(new \Laiz\Func\Unit()), $ret);


        $manyP = skipMany1($parser);
        $ret = parse($manyP, "Test", "abcabcd");
        $this->assertEquals(Right(new \Laiz\Func\Unit()), $ret);

        $ret = parse($manyP, "Test", "def");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected d/', show($err));
    }

    public function testOption()
    {
        $parser = str('abc');
        $optP = option('xyz', $parser);
        $ret = parse($optP, "Test", "abcabcd");
        $this->assertEquals(Right('abc'), $ret);

        $ret = parse($optP, "Test", "def");
        $this->assertEquals(Right('xyz'), $ret);


        $optP = optional($parser);
        $ret = parse($optP, "Test", "abcabcd");
        $this->assertEquals(Right(new \Laiz\Func\Unit()), $ret);

        $optP = optional($parser);
        $ret = parse($optP, "Test", "def");
        $this->assertEquals(Right(new \Laiz\Func\Unit()), $ret);
    }


    public function testChoice()
    {
        $parser = choice([str('abc'), str('ABC'), str('def')]);
        $ret = parse($parser, "Test", "abcz");
        $this->assertEquals(Right('abc'), $ret);

        $ret = parse($parser, "Test", "defz");
        $this->assertEquals(Right('def'), $ret);

        $ret = parse($parser, "Test", "ABCz");
        $this->assertEquals(Right('ABC'), $ret);

        $ret = parse($parser, "Test", "zzz");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected z/', show($err));
    }

    public function testBetween()
    {
        $parser = between(char('{'), char('}'), str('abc'));
        $ret = parse($parser, "Test", "{abc}z");
        $this->assertEquals(Right('abc'), $ret);

        $ret = parse($parser, "Test", "{abcd}z");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected d/', show($err));
        $this->assertRegExp('/expecting }/', show($err));
    }

    public function testSepBy()
    {
        $alnum = satisfy(function($s){
            return ctype_alnum($s);
        });
        $p = flat(many1($alnum));
        $p1 = sepBy1($p, char(','));
        $ret = parse($p1, "Test", "abc,A03,zzz@a");
        $this->assertEquals(Right(['abc', 'A03', 'zzz']), $ret);

        $ret = parse($p1, "Test", "abc,A03,zzz,");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected end of input/', show($err));


        $ret = parse($p1, "Test", "");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected end of input/', show($err));

        $p2 = sepBy($p, char(','));
        $ret = parse($p2, "Test", "");
        $this->assertEquals(Right([]), $ret);
    }

    public function testSepEndBy()
    {
        $alnum = satisfy(function($s){
            return ctype_alnum($s);
        });
        $p = flat(many1($alnum));
        $p1 = sepEndBy1($p, char(','));
        $ret = parse($p1, "Test", "abc,A03,zzz@a");
        $this->assertEquals(Right(['abc', 'A03', 'zzz']), $ret);

        $ret = parse($p1, "Test", "abc,A03,zzz,");
        $this->assertEquals(Right(['abc', 'A03', 'zzz']), $ret);

        $ret = parse($p1, "Test", "");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected end of input/', show($err));

        $p2 = sepEndBy($p, char(','));
        $ret = parse($p2, "Test", "");
        $this->assertEquals(Right([]), $ret);

        $p2 = sepEndBy($p, char(','));
        $ret = parse($p2, "Test", ",");
        $this->assertEquals(Right([]), $ret);
    }
    public function testEndBy()
    {
        $alnum = satisfy(function($s){
            return ctype_alnum($s);
        });
        $p = flat(many1($alnum));
        $p1 = endBy1($p, char(','));
        $ret = parse($p1, "Test", "abc,A03,zzz@a");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected @/', show($err));

        $ret = parse($p1, "Test", "abc,A03,zzz,");
        $this->assertEquals(Right(['abc', 'A03', 'zzz']), $ret);

        $ret = parse($p1, "Test", "");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected end of input/', show($err));

        $p2 = endBy($p, char(','));
        $ret = parse($p2, "Test", "");
        $this->assertEquals(Right([]), $ret);

        $ret = parse($p2, "Test", ",");
        $this->assertEquals(Right([]), $ret);
    }

    public function testEof()
    {
        $parser = eof();

        $ret = parse($parser, "Test", "abAB");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected a/', show($err));

        $ret = parse($parser, "Test", "");
        $this->assertEquals(Right(new Func\Unit()), $ret);

        $p2 = Func\Applicative\const1(str('abc'), eof());
        $ret = parse($p2, "Test", "abc");
        $this->assertEquals(Right('abc'), $ret);

        $p2 = Func\Applicative\const1(str('abc'), eof());
        $ret = parse($p2, "Test", "abcd");
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp('/unexpected d/', show($err));
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
