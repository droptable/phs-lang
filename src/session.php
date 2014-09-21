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
require_once 'front/analyze.php';
require_once 'front/scope.php';
require_once 'front/format.php';

use phs\front\Analyzer;
use phs\front\Resolver;
use phs\front\Location;
use phs\front\AstFormatter;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\Scope;
use phs\front\GlobScope;
use phs\front\RootScope;
use phs\front\UnitScope;
use phs\front\ModuleScope;

class Session
{
  // @var Config
  public $conf;
  
  // @var string  the root-directory used to import other files
  public $rpath;
  
  // abort flag
  public $aborted;
  public $abortloc;
  
  public $started = false;
  
  // @var Dict  use-lookup cache dict
  public $udct;
  
  // files handled in this session.
  // includes imports via `use xyz;`
  public $files;
  
  // @var Scope  global scope
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
    $this->rpath = $root;
    $this->abort = false;
    
    $this->scope = new GlobScope; // global scope    
    $this->udct = new Dict; // use-lookup-cache
    $this->queue = new SourceSet; // to-be parsed files
    $this->files = new SourceSet; // already parsed files
    $this->units = new Set;
  }
  
  /**
   * adds a file
   *
   * @param string $path
   */
  public function add_file($path)
  {
    $this->add_source(new FileSource($path));
  }
  
  /**
   * add a source
   * 
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    if ($src->check() && $this->files->add($src)) {
      if ($this->started)
        // in compilation: source may be added from a
        // require-declaration -> compile it now
        $this->process($src);
      else
        // add file to the compile-queue
        $this->queue->add($src);
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
  
  /**
   * starts compiling
   *
   * @return void
   */
  public function compile()
  {
    $this->started = true;
    
    // ---------------------------------------
    // phase 1: analyze (process) sources
    
    while ($src = $this->queue->shift())
      $this->process($src);
    
    
    // ---------------------------------------
    // phase 2: 
        
    if ($this->aborted) {
      Logger::error('compilation aborted due to previous error(s)');
      return;
    }
    
    Logger::debug('complete');
        
    foreach ($this->files as $file)
      Logger::debug('using file %s', $file->get_path());
  }
  
  /**
   * process a unit
   *
   * @param  Source $src
   */
  protected function process(Source $src)
  {
    if ($src->php)
      return;
    
    $uanl = new Analyzer($this);
    $afmt = new AstFormatter($this);
    $unit = $uanl->analyze($src);
    
    if ($unit) {
      $this->units->add($unit);
      
      #echo "\n";
      #$unit->scope->dump('');
      #echo "\n\n";
      #echo $afmt->format($unit);
    }
  }
}

