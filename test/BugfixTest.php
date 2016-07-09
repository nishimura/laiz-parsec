<?php

namespace Laiz\Test\Parsec;

use Laiz\Parsec;
use function Laiz\Parsec\parse;
use function Laiz\Func\Either\Right;

use function Laiz\Parsec\tryP;
use function Laiz\Parsec\satisfy;
use function Laiz\Parsec\char;
use function Laiz\Parsec\str;
use function Laiz\Parsec\optional;


Parsec\Char\load();
Parsec\Combinator\load();

class BugfixTest extends \PHPUnit_Framework_TestCase
{
    public function testCombinatorBind()
    {

        $tag = char('a');
        $context = optional(char('@'))->const2(tryP($tag));
        $parser = $context->aor(char('#'));
        $ret = parse($parser, "Test", '####b');
        $this->assertEquals(Right('#'), $ret);
    }
}
