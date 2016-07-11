<?php

namespace Laiz\Test\Parsec;

use Laiz\Func\Maybe;
use Laiz\Parsec;
use Laiz\Parsec\SourcePos;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\many1;
use function Laiz\Parsec\many;
use function Laiz\Parsec\tokenPrim;
use function Laiz\Parsec\Show\show;
use function Laiz\Func\Either\Right;

function half()
{
    return tokenPrim(function($t){
        return (string)$t;
    }, function($pos, $t, $ts){
        return new SourcePos($pos->name(), $pos->line(), $pos->col() + 1);
    }, function($t){
        if ($t % 2 === 0)
            return new Maybe\Just($t / 2);
        else
            return new Maybe\Nothing();
    });
}

class ArrayParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParserToken()
    {
        $parser = half();

        $ret = parse($parser, "Test", [8, 5]);
        $this->assertEquals(Right(4), $ret);

        $ret = parse($parser, "Test", [3, 4, 5]);
        $err = null;
        $ret->either(function($a) use (&$err){
            $err = $a;
        }, function($a){});
        $this->assertRegExp("/unexpected 3/", show($err));
    }

    public function testMany()
    {
        $parser = half();

        $ret = parse(many1($parser), "Test", [8, 10, 9, 5]);
        $this->assertEquals(Right([4, 5]), $ret);


        $ret = parse(many($parser), "Test", [1, 8, 10, 9, 5]);
        $this->assertEquals(Right([]), $ret);
    }
}
