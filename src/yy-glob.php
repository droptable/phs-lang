<?php

namespace phs;

// token 
class Token
{
  public $loc;
  public $raw;
  public $type;
  public $value;
  
  public function __construct($type, $value)
  {
    $this->type = $type;
    $this->value = $value;
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
    print "{$this->file} {$this->pos->line}:{$this->pos->coln}\n";
  }
}
