Laiz Parsec
===========



[![Build Status](https://travis-ci.org/nishimura/laiz-parsec.svg?branch=master)](https://travis-ci.org/nishimura/laiz-parsec)
[![Coverage Status](https://coveralls.io/repos/github/nishimura/laiz-parsec/badge.svg?branch=master)](https://coveralls.io/github/nishimura/laiz-parsec?branch=master)

[![Code Climate](https://codeclimate.com/github/nishimura/laiz-parsec/badges/gpa.svg)](https://codeclimate.com/github/nishimura/laiz-parsec)


## String Parser

```php
require 'vendor/autoload.php';

use Laiz\Func\Either;
use Laiz\Parsec;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\str;

Parsec\Char\load();

$parser = str('abc');

$ret = parse($parser, "Test", "abcdef");
var_dump($ret);
// Right abc
```

## Combinator

```php
require 'vendor/autoload.php';

use Laiz\Func\Either;
use Laiz\Parsec;
use function Laiz\Func\Applicative\const1;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\satisfy;
use function Laiz\Parsec\flat;
use function Laiz\Parsec\many1;
use function Laiz\Parsec\endBy1;
use function Laiz\Parsec\char;
use function Laiz\Parsec\str;
use function Laiz\Parsec\eof;

Parsec\Char\load();
Parsec\Combinator\load();

$alnum = satisfy(function($s){
    return ctype_alnum($s);
});
$p = flat(many1($alnum));
$ret = parse($p, "Test", "abc,A03,zzz,@a");
var_dump($ret);
// Right "abc"

$p1 = endBy1($p, char(','));
$ret = parse($p1, "Test", "abc,A03,zzz,@a");
var_dump($ret);
// Right ["abc", "A03", "zzz"]

$p = eof();
$ret = parse($p, "Test", "abc");
var_dump($ret);
// Left ParseError

$p = eof();
$ret = parse($p, "Test", "");
var_dump($ret);
// Right Unit

$p = const1(str('abc'), eof()); // string "abc" *> eof
$ret = parse($p, "Test", "abc");
var_dump($ret);
// Right "abc"

$ret = parse($p, "Test", "abcd");
var_dump($ret);
// Left ParseError
```

## HTML Parser

```php
require 'vendor/autoload.php';

use Laiz\Func\Either;
use Laiz\Parsec;
use function Laiz\Func\Applicative\pure;
use function Laiz\Parsec\parse;
use function Laiz\Parsec\tryP;
use function Laiz\Parsec\satisfy;
use function Laiz\Parsec\anyChar;
use function Laiz\Parsec\flat;
use function Laiz\Parsec\many;
use function Laiz\Parsec\many1;
use function Laiz\Parsec\char;
use function Laiz\Parsec\str;
use function Laiz\Parsec\choice;
use function Laiz\Parsec\between;
use function Laiz\Parsec\notFollowedBy;
use function Laiz\Parsec\parserReturn;
use function Laiz\Parsec\sepEndBy;
use function Laiz\Parsec\Show\show;

Parsec\Char\load();
Parsec\Combinator\load();

class Attr
{
    public $name;
    public $value;
    public function __construct($n, $v){
        $this->name = $n;
        $this->value = $v;
    }
}
class TagInfo
{
    public $name;
    public $attrs = [];
    public function __construct($n, $a = []){
        $this->name = $n;
        $this->attrs = $a;
    }
}
class Tag
{
    public $name;
    public $attrs = [];
    public $children = [];
    public function __construct($i, $c = []){
        $this->name = $i->name;
        $this->attrs = $i->attrs;
        $this->children = $c;
    }
}
class Plain
{
    public $content;
    public function __construct($c){
        $this->content = $c;
    }
}

$html = '
  <div>
    <a href="/user/info.php">User Info</a>
    <a href="/admin/" class="btn">Admin</a>
  </div>
';

function not($n){
    return satisfy(function($s) use ($n){
        return $s !== $n;
    });
}
function alnum(){
    return satisfy(function($s){
        return ctype_alnum($s);
    });
}
function space(){
    return satisfy(function($s){
        return strpos(" \r\n\t", $s) !== false;
    });
}
function spaces(){ return flat(many(space())); }
function spaces1(){ return flat(many1(space())); }


// Parser s u Tag
function tag(){
    $attrName = flat(many1(alnum()));
    $eq = char('=');
    $attrValue = between(char('"'), char('"'),
                         flat(many1(not('"'))));
    $attr = $attrName->bind(function($name) use ($eq, $attrValue){
        return $eq->const2($attrValue)->bind(function($value) use ($name){
            return parserReturn(new Attr($name, $value));
        });
    });
    //var_dump(parse($attr, "Test", 'foo="bar1">'));


    $tagName = flat(many1(alnum()))->const1(spaces());
    $tagContent = $tagName->bind(function($name) use ($attr){
        return sepEndBy($attr, spaces1())->bind(function($attrs) use ($name){
            return parserReturn(new TagInfo($name, $attrs));
        });
    });
    $tagOpen = between(char('<'), char('>'), $tagContent);
    //var_dump(parse($tagOpen, "Test", '<span>'));
    //var_dump(parse($tagOpen, "Test", '<span class="bar1" style="display:inline;">'));
    return $tagOpen->bind(function($info){
        return many(context())->bind(function($context) use ($info){
            $tagClose = str('</' . $info->name . '>');
            return $tagClose->bind(function($_) use ($info, $context){
                return parserReturn(new Tag($info, $context));
            });
        });
    });
}

// Parser s u Plain
function plain(){
    return flat(many1(not('<')))->bind(function($plain){
        return parserReturn(new Plain($plain));
    });
}
// Parser s u (Tag|Plain)
function context(){
    return spaces()->const2(choice([plain(), tryP(tag())]))->const1(spaces());
}
//var_dump(parse(tag(), "Test", '<div>test</div>'));
//var_dump(parse(many1(context()), "Test", 'aa<div><span class="a">test</span></div>'));


$parser = many1(context());
$ret = parse($parser, "Test", $html);

$ret->either(function($left){
    var_dump(show($left));
}, function($right){
    var_dump($right);
});

// If error: "PHP Fatal error:  Maximum function nesting level of '100' reached, aborting!" error occurs
// php.ini "xdebug.max_nesting_level = 1500"


/*
array(1) {
  [0] =>
  class Tag#835 (3) {
    public $name =>
    string(3) "div"
    public $attrs =>
    array(0) {
    }
    public $children =>
    array(2) {
      [0] =>
      class Tag#6817 (3) {
        public $name =>
        string(1) "a"
        public $attrs =>
        array(1) {
          [0] =>
          class Attr#4598 (2) {
            public $name =>
            string(4) "href"
            public $value =>
            string(14) "/user/info.php"
          }
        }
        public $children =>
        array(1) {
          [0] =>
          class Plain#6263 (1) {
            public $content =>
            string(9) "User Info"
          }
        }
      }
      [1] =>
      class Tag#2002 (3) {
        public $name =>
        string(1) "a"
        public $attrs =>
        array(2) {
          [0] =>
          class Attr#7011 (2) {
            public $name =>
            string(4) "href"
            public $value =>
            string(7) "/admin/"
          }
          [1] =>
          class Attr#5179 (2) {
            public $name =>
            string(5) "class"
            public $value =>
            string(3) "btn"
          }
        }
        public $children =>
        array(1) {
          [0] =>
          class Plain#2864 (1) {
            public $content =>
            string(5) "Admin"
          }
        }
      }
    }
  }
}

 */
```

