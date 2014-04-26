<?php

namespace phs;

require 'util/set.php';
require 'front/scope.php';

use phs\util\Set;
use phs\front\Scope;

class Session
{
  // @var Config
  public $conf;
  
  // abort flag
  public $abort;
  
  // files handled in this session.
  // includes imports via `use xyz;`
  public $files;
  
  // global scope
  public $scope;
  
  public function __construct(Config $conf)
  {
    $this->conf = $conf;
    $this->abort = false;
    $this->files = new Set;
    $this->scope = new Scope;
  }
}

