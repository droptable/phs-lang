<?php

namespace phs;

require_once 'util/set.php';
require_once 'front/glob.php';
require_once 'front/scope.php';

use phs\util\Set;
use phs\front\Scope;
use phs\front\Location;

class Session
{
  // @var Config
  public $conf;
  
  // @var string  the root-directory used to import other files
  public $root;
  
  // abort flag
  public $aborted;
  public $abortloc;
  
  // files handled in this session.
  // includes imports via `use xyz;`
  public $files;
  
  // global scope
  public $scope;
  
  /**
   * constructor
   * @param Config $conf
   * @param string $root
   */
  public function __construct(Config $conf, $root)
  {
    $this->conf = $conf;
    $this->root = $root;
    $this->abort = false;
    $this->files = new Set;
    $this->scope = new Scope;
  }
  
  /**
   * aborts the current session
   * @param  Location $loc
   * @return void
   */
  public function abort(Location $loc = null)
  {
    if ($loc && !$this->abortloc)
      $this->abortloc = $loc;
    
    $this->aborted = true;
  }
}

