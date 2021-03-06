<?php

namespace phs;

const VERSION = '0.1a1';

// token 
class Token
{
  public $uid;
  public $loc;
  public $raw;
  public $flag;
  public $type;
  public $value;
  public $delim;
  
  private static $uid_base = 0;
  
  public function __construct($type, $value)
  {
    $this->type = $type;
    $this->value = $value;
    $this->uid = ++self::$uid_base;
  }
  
  public function __clone()
  {
    $this->loc = clone $this->loc;
  }
  
  public function debug()
  {
    $this->loc->debug();
    print "tok: {$this->type}\n";
    print "value: {$this->value}\n";
  }
}

// position
class Position
{
  public $line;
  public $coln;
  
  public function __construct($line, $coln)
  {
    $this->line = $line;
    $this->coln = $coln;
  }
  
  public function __clone() 
  {
    // nothing to clone
  }
}

// location
class Location
{
  public $file;
  public $pos;
  
  public function __construct($file, Position $pos)
  {
    $this->file = $file;
    $this->pos = $pos;
  }
  
  public function __clone()
  {
    $this->pos = clone $this->pos;
  }
  
  public function debug()
  {
    print $this->__toString() . "\n";;
  }
  
  public function __toString()
  {
    return "{$this->file}:{$this->pos->line}:{$this->pos->coln}";
  }
}

