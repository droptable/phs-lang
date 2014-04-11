#!/usr/bin/php
<?php

namespace gen;

/* this script takes all "@Example(...)" expressions in the grammar,
   replaces them to "new Example(...)" and
   adds a require-statement + use-statement with alias
   to the parser 
   
   foo
    : bar { $$ = @Baz($1); }
    ;
    
   -> require 'ast/baz.php';
   -> use phs\ast\Baz as BazNode; 
   
   foo
    : bar { $$ = new BazNode($1); }
    ;
   
   */
   
// ---------------------------
   
/**
 * an alias use-statement is required to avoid naming-conflicts
 * -> a class can not have the same name as a grammar-rule.
 *  
 * if a conflict occurs: just update the `alias` function to generate
 * a better (unique) alias-name 
 *   
 * @param string $name the used class-name
 * @return string the aliased class-name
 */
function alias($name) {
  return $name;
}

// ---------------------------

$I = __DIR__ . '/parser.y';
$O = __DIR__ . '/parser-tmp.y';

class Context { 
  public $buf, $len, $idx, $use;
  public $req, $imp; 
  public $opts;
}

$ctx = new Context;
$ctx->opts = parse_opts($_SERVER['argc'], $_SERVER['argv']);

if (isset ($ctx->opts['?']) || 
    isset ($ctx->opts['-h']) ||
    isset ($ctx->opts['--help']))
  usage();

$data = file_get_contents($I);

// prepare context
prepare($data, $ctx);
unset ($data);

// generate temp-grammer
generate($ctx);

// deploy
deploy($O, $ctx);

// ---------------------------

function deploy($O, $ctx) {
  file_put_contents($O, $ctx->buf);
  
  $P = __DIR__ . '/' . basename($O, '.y') . '.php';
  if (is_file($P)) unlink($P);
  
  $t = !empty ($ctx->opts['--debug']) ? '-t' : '';
  $y = `kmyacc -v -m "parser.sk" $t -p "Parser" -l -L php "$O" 2>&1`;
  
  if (!empty($y)) {
    print "\nkmyacc returned with error(s):\n";
    exit($y);
  }
  
  $F = file_get_contents($P);
  $F = preg_replace('/^\/\*@@\s*imports\s*@@\*\//m', $ctx->imp, $F);
  file_put_contents($P, $F);
  
  verbose($ctx,
<<<END_PHS
     ___  __ ______
    / _ \/ // / __/
   / ___/ _  /\ \  
  /_/  /_//_/___/
  
END_PHS
  );
  
  $S = realpath(__DIR__ . '/..');
  rename($P, "$S/parser.php");
  verbose($ctx, "-> $S/parser.php");
  
  foreach ([ 'parser-tmp.php', 'parser-tmp.y' ] as $f)
    if (is_file($f = __DIR__ . "/$f")) unlink($f);
  
  $ast = <<<END_AST
<?php

namespace phs\\front;

/**
 * This is an automatically GENERATED file!
 * See: src/front/yacc/build.php
 */
require_once 'ast/node.php';
require_once 'ast/decl.php';
require_once 'ast/stmt.php';
require_once 'ast/expr.php';
{$ctx->req}

END_AST;
  
  file_put_contents("$S/ast.php", $ast);
  verbose($ctx, "-> $S/ast.php");
}

function verbose($ctx, $msg) {
  if (empty ($ctx->opts['-v']) && 
      empty ($ctx->opts['--verbose']))
    return;
  
  echo $msg, "\n";
}

function parse_opts($argc, $argv) {
  $opts = [];
  
  for ($i = 1; $i < $argc; ++$i) {
    $key = $argv[$i];
    $val = true;
    
    if (strpos($key, '=') !== false)
      list ($key, $val) = explode('=', $key, 2);
    
    $opts[$key] = $val;
  }
  
  return $opts;
}

function usage() {
  exit(
<<<END_USE
usage:
gen [ ? | -h | --help | -v | --verbose ]

generates the phs parser (yy-parser.php) and the ast-import file (yy-ast.php)

? -h --help     shows this message
-v --verbose    outputs some informations while processing
   
END_USE
  );
}

function generate($ctx) {
  $i = '';
  $r = '';
  $t = [];
  
  foreach ($ctx->use as $use) {
    $n = $use[0];
    $a = $use[1];
    
    if (in_array($n, $t))
      continue;
    
    $s = '';
    if ($a !== $n)
      $s = "as $a";
    
    $p = 'ast/' . path($n);
    
    $t[] = $n;
    $i .= "use phs\\front\\ast\\$n$s;\n";
    $r .= "require_once '$p';\n";
    
    verbose($ctx, 'using "' . $n . "' with alias " 
      . ($s ?: '(none)') . ' at ' . $p);
  }
  
  $ctx->req = $r;
  $ctx->imp = $i;
  
  verbose($ctx, '... found ' . count($t) . ' unique ast-nodes');
}

function path($n) {
  $n = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $n);
  return strtolower($n) . '.php';
}

function prepare(&$data, $ctx) {
  $ctx->buf = '';
  $ctx->len = strlen($data);
  $ctx->idx = 0;
  $ctx->use = [];
  
  compile($data, $ctx);
}

function compile(&$data, $ctx) {
  $i = &$ctx->idx;
  $l = &$ctx->len;
  $b = &$ctx->buf;
  $q = 0;
  $s = 0;
  $e = false;
  $o = false;
  
  for (; $i < $l; ++$i) {
    $c = $data[$i];
    
    if ($o) {
      if ($c === "\n")
        $o = false;
      $b .= $c;
    } else {
      if ($q === 0) {
        if ($c === '/') {
          $b .= $c;
          if ($s === 1) {
            $o = true;
            $s = 0;
          } else
            $s = 1; 
        } elseif ($c === '*' && $s === 1) {
          $b .= $c;
          for ($n = false, ++$i; $i < $l; ++$i) {
            $b .= $c = $data[$i];
            if ($n && $c === '/')
              break;
            $n = $c === '*';
          }
          $s = 0;
        } elseif ($c === '{') 
          parse($data, $ctx);
        else {
          if ($c === '"' || $c === "'")
            $q = $c;
          $b .= $c;
        }
      } else {
        if ($c === $q) {
          if (!$e) 
            $q = 0;
          $e = false;
        } elseif ($c === '\\')
          $e = !$e;
        $b .= $c;
      }
    }
  }
}

function parse(&$data, $ctx) {
  $i = &$ctx->idx;
  $l = &$ctx->len;
  $u = &$ctx->use;
  $q = 0;
  $e = false;
  $b = '';
  
  for (; $i < $l; ++$i) {
    $c = $data[$i];
    $b .= $c;
    
    if ($q === 0) {
      if ($c === '}')
        break;
      if ($c === '"' || $c === "'")
        $q = $c;
    } else {
      if ($q === $c) {
        if (!$e) 
          $q = 0;
        $e = false;
      } elseif ($c === '\\')
        $e = !$e;
    }
  }
  
  $ctx->buf .= preg_replace_callback(
    '/@([a-zA-Z_][a-zA-Z_0-9]*)\s*/',
    function ($m) use (&$ctx, &$loc) {
      $n = $m[1];
      $a = alias($n);      
      $r = "new $a"; 
              
      $ctx->use[] = [ $n, $a ];
      return $r;
    },
    $b
  );
}

