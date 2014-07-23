<?php

namespace phs;

require_once 'logger.php';
require_once 'source.php';

require_once 'util/set.php';
require_once 'util/map.php';
require_once 'util/dict.php';

use phs\util\Set;
use phs\util\Map;
use phs\util\Dict;

require_once 'front/glob.php';
require_once 'front/ast.php';
require_once 'front/lexer.php';
require_once 'front/parser.php';
require_once 'front/analyze.php';
require_once 'front/scope.php';

use phs\front\Parser;
use phs\front\Analyzer;
use phs\front\Analysis;
use phs\front\Location;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\Scope;
use phs\front\RootScope;
use phs\front\UnitScope;
use phs\front\ModuleScope;

class Session
{
  // @var Config
  public $conf;
  
  // @var string  the root-directory used to import other files
  public $root;
  
  // abort flag
  public $aborted;
  public $abortloc;
  
  public $started = false;
  
  // @var Dict  use-lookup cache dict
  public $udct;
  
  // files handled in this session.
  // includes imports via `use xyz;`
  public $files;
  
  // global scope
  public $scope;
  
  // @var SourceSet  assigned sources
  private $queue;
  
  // @var Set  assigned units
  private $units;
  
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
    
    $this->scope = new Scope; // global scope
    
    $this->udct = new Dict; // use-lookup-cache
    $this->queue = new SourceSet; // to-be parsed files
    $this->files = new SourceSet; // already parsed files
    $this->units = new Set;
  }
  
  /**
   * add a unit (ast)
   * @param Unit $unit
   */
  public function add_unit(Unit $unit)
  {
    assert(0);
  }
  
  /**
   * add a source
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    if ($this->files->add($src)) {
      $this->queue->add($src);
      
      // already compiling? -> parse now
      if ($this->started)
        $this->parse_queue();
    }
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
  
  public function compile()
  {
    $this->started = true;
    
    // phase 1
    if (!$this->measure('syntax analysis', function() {      
      $this->parse_queue();
    })) return;
    
    // phase 2
    if (!$this->measure('semantic analysis', function() {
      $this->analyze_units();
    })) return;
      
    foreach ($this->files as $file) {
      Logger::debug('using file %s', $file->get_path());
    }
  }
  
  /**
   * does a compile-phase callback with timing-measures
   *
   * @param  string   $type
   * @param  callable $func
   * @return boolean
   */
  protected function measure($type, callable $func)
  {    
    $time = microtime(true);
    $func();
    $done = microtime(true) - $time;
    
    Logger::debug('%s took %fs', $type, $done);
    
    if ($this->aborted) {
      Logger::debug('aborted', $type);
      return false;
    }
    
    return true;
  }
  
  /**
   * parses all available sources
   *
   * @return void
   */
  protected function parse_queue()
  {
    $psr = new Parser($this);
    
    // parse all sources
    while ($src = $this->queue->shift()) {
      $unit = $psr->parse($src);
      
      if ($unit)
        $this->units->add($unit);
      
      // ignore result and continue parsing to 
      // report as much errors as possible
    }
  }
  
  /**
   * analyzes all units
   *
   * @return void
   */
  protected function analyze_units()
  {
    $anl = new Analyzer($this);
    
    // analyze all units 
    while ($unit = $this->units->shift()) {
      $ares = $anl->analyze($unit);
      $ares->dump();
    }
  }
}

