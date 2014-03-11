<?php

namespace phs;

// token 
class Token
{
  public $uid;
  public $loc;
  public $raw;
  public $type;
  public $value;
  
  private static $uid_base = 0;
  
  public function __construct($type, $value)
  {
    $this->type = $type;
    $this->value = $value;
    $this->uid = ++self::$uid_base;
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
  
  public function debug()
  {
    print $this->__toString() . "\n";;
  }
  
  public function __toString()
  {
    return "{$this->file}:{$this->pos->line}:{$this->pos->coln}";
  }
}

// label
class Label 
{
  public $id;
  public $loc;
  public $breakable;
  public $reachable;
  
  public function __construct($id, Location $loc)
  {
    $this->id = $id;
    $this->loc = $loc;
    $this->breakable = true; // default
    $this->reachable = true; // default
  }
}

// goto
class LGoto
{
  public $id;
  public $loc;
  public $resolved;
  
  public function __construct($id, Location $loc)
  {
    $this->id = $id;
    $this->loc = $loc;
    $this->resolved = false; // default
  }
}
