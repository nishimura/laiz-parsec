<?php

namespace Laiz\Test\Parsec\Stream;

use Laiz\Func\Maybe;

class ListStrings implements \Laiz\Parsec\Stream
{
    public static function uncons($a)
    {
        if ($a->a === null)
            return new Maybe\Nothing();

        return new Maybe\Just([$a->a, $a->as]);
    }
}


namespace Laiz\Test\Parsec;

use Laiz\Func\Maybe;
use Laiz\Parsec;
use Laiz\Parsec\SourcePos;
use Laiz\Parsec\Stream;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\many1;
use function Laiz\Parsec\many;
use function Laiz\Parsec\tokenPrim;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Either\Right;

class ListString
{
    public $a;
    public function __construct($a)
    {
        $this->a = $a;
    }
}

class ListStrings
{
    public $a;
    public $as;
    public function __construct($a, $as) {
        $this->a = $a;
        $this->as = $as;
    }
}

function satisfy($f)
{
    return tokenPrim(function($t){
        return $t->a;
    }, function($pos, $t, $ts){
        return new SourcePos($pos->name(), $pos->line(), $pos->col() + 1);
    }, function($t) use ($f){
        if ($f($t))
            return new Maybe\Just($t->a);
        else
            return new Maybe\Nothing();
    });
}

class StreamParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParserList()
    {
        $test = new ListStrings(
            new ListString('t'),
            new ListStrings(
                new ListString('e'),
                new ListStrings(
                    new ListString('s'),
                    new ListStrings(
                        new ListString('t'),
                        new ListStrings(null, null)))));
        
        $t = satisfy(function($t){
            return $t->a === 't';
        });

        $ret = parse($t, "Test", $test);
        $this->assertEquals(Right('t'), $ret);

        $e = satisfy(function($t){
            return $t->a === 'e';
        });
        $s = satisfy(function($t){
            return $t->a === 's';
        });

        $parser = $t->mappend($e)->mappend($s)->mappend($t);
        $ret = parse($parser, "Test", $test);
        $this->assertEquals(Right('test'), $ret);

        $tes = new ListStrings(
            new ListString('t'),
            new ListStrings(
                new ListString('e'),
                new ListStrings(
                    new ListString('s'),
                    new ListStrings(null, null))));
        $ret = parse($parser, "Test", $tes);
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unexpected end of input/", show($err));

        $parser = $t->mappend($e)->mappend($e);
        $ret = parse($parser, "Test", $tes);
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unexpected s/", show($err));
    }
}
