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

require_once 'glob.php';
require_once 'ast.php';
require_once 'scope.php';
require_once 'compile.php';
require_once 'bundle.php';

use phs\ast\Node;
use phs\ast\Unit;

const 
  KIND_NON = 0,
  KIND_SRC = 1,
  KIND_LIB = 2
;

class Session
{
  // @var bool  whenever the runtime was initialized
  private static $init = false;
  
  // @var Config
  public $conf;
  
  // abort flag
  public $aborted;
  public $abortloc;
  
  public $started = false;
  
  // @var int  current kind of file
  private $kind = KIND_NON;
  
  // @var string  source root-directory (used to pack)
  public $sroot;
  
  // @var Source  main source
  public $main;
  
  // @var Dict  use-lookup cache dict
  public $udct;
  
  // files handled in this session.
  // includes imports via `use xyz;`
  public $srcs;
  
  // files added as library
  public $libs;
  
  // @var Scope  global scope
  public $scope;
  
  // @var SourceSet  assigned sources
  private $queue;
  
  // @var Set  assigned units
  private $units;
  
  // @var Symbol  root-object class
  // note: only defined if "nostd" is false
  public $robj;
  
  // @var Compiler
  private $comp;
  
  /**
   * constructor
   * @param Config $conf
   * @param string $root
   */
  public function __construct(Config $conf)
  {    
    if (!self::$init) {
      Logger::init($conf);
      self::$init = true;  
    }
        
    $this->conf = $conf;
    $this->abort = false;
    
    $this->scope = new GlobScope; // global scope
    $this->units = new Set;
    
    $this->srcs = new SourceSet;
    $this->libs = new SourceSet;
    
    // process-queue
    $this->queue = [];
    
    if (!$this->conf->nort)
      $this->setup_rt();
    
    $this->comp = new Compiler($this);
    
    // hook logger    
    Logger::hook(LOG_LEVEL_ERROR, [ $this, 'abort' ]);
    
    if ($this->conf->werror === true)
      Logger::hook(LOG_LEVEL_WARNING, [ $this, 'abort ']);
  }
  
  /**
   * pre-defines some built-in symbols of the runtime
   *
   */
  protected function setup_rt()
  {
    $loc = new Location('<built-in>', new Position(0, 0));
    $pub = SYM_FLAG_PUBLIC;
    $non = SYM_FLAG_NONE;
    
    /**
     * the root-object class.
     * every class has `Obj` as a superclass.
     * 
     * @see lib/run.php
     * @see lib/run/obj.php
     */
    $obj = new ClassSymbol('Obj', $loc, $non);
    $obj->members = new MemberScope($obj, $this->scope);
    $obj->managed = true;
    $obj->resolved = true;
    
    /**
     * constructor
     * 
     */
    $ctor = new FnSymbol('<ctor>', $loc, $pub);
    $ctor->ctor = true;
    $obj->members->ctor = $ctor;
    
    /**
     * returns the object-hash
     * 
     * @return string
     */
    $obj->members->add(new FnSymbol('hash', $loc, $pub));
    
    /* ------------------------------------ */
    
    $this->scope->add($obj);
    $this->robj = $obj;
  }
  
  /**
   * adds a source from a string
   *
   * @param string $str
   */
  public function add_source_from($str)
  {
    $path = getcwd() . DIRECTORY_SEPARATOR . ltrim($str, '/\\');
    $path = realpath($path);
    
    if (!$path || !is_file($path))
      Logger::error('file not found: %s', $path);
    
    $this->add_source(new FileSource($path));
  }
  
  /**
   * adds a library from a string
   *
   * @param string $str
   */
  public function add_library_from($str)
  {
    $done = false;
    $file = strtolower(substr($str, -4)) === '.phs';
    
    foreach ($this->conf->lib_paths as $libp) {
      $root = $libp . DIRECTORY_SEPARATOR;
      
      if ($file) {
        $path = $root . $str;
        if (!is_file($path)) continue;
        goto add;
      }
      
      // pass 1) $root + $str + '.phs'
      $path = $root . $str . '.phs';
      if (is_file($path)) goto add;
      
      // pass 2) $root + $str '/lib.phs'
      $path = $root . $str . DIRECTORY_SEPARATOR . 'lib.phs';
      if (is_file($path)) goto add;
      
      continue;
      
      add:
      $this->add_library(new FileSource($path));
      $done = true;
      break;
    }
    
    if (!$done)
      Logger::error('library not found: %s', $str);
  }
  
  /**
   * add a source
   * 
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    if ($src->check() && $this->srcs->add($src)) {
      // first source-file = main
      if ($this->main === null)
        $this->main = $src;
      
      // add to queue
      $this->queue[] = [ $src, KIND_SRC ]; 
    }
  }
  
  /**
   * adds a library
   *
   * @param Source $src
   */
  public function add_library(Source $src)
  {
    if ($src->check() && $this->libs->add($src))
      // add to queue
      $this->queue[] = [ $src, KIND_LIB ];
  }
  
  /**
   * adds a imported source
   * this function is normally just called by the compiler (resolve.php)
   *
   * @param Source $src
   */
  public function add_import(Source $src)
  {
    assert($this->started);
    
    // update source-root
    $src->set_root($this->sroot);
    $src->import = true;
    
    $root = $src->get_root();
    $path = $src->get_path();
    
    if (strpos($path, $root) !== 0) {
      $loc = $src->loc;
      Logger::error_at($loc, 'unable to import %s', $path);
      Logger::error_at($loc, 'current root-path is %s', $root); 
      Logger::info('you either have to copy this file into the shown \\');
      Logger::info('root-path or add it as a library which \\');
      Logger::info('gets included automatically for you');
      return;
    } 
    
    if (!$src->check())
      return;
    
    $res = false;
    
    switch ($this->kind) {
      case KIND_LIB:
        $res = $this->libs->add($src);
        break;
      case KIND_SRC:
        $res = $this->srcs->add($src);
        break;
    }
    
    if ($res === true)
      $this->analyze($src);
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
  public function process()
  {
    $this->started = true;
    
    if ($this->aborted)
      goto err;
    
    // ---------------------------------------
    // step 1: analyze sources 
    
    while (list ($src, $kind) = array_shift($this->queue)) {
      if ($kind !== $this->kind || $kind === KIND_LIB)
        // unset source-root
        $this->sroot = null;
      
      $this->kind = $kind;  
      $this->analyze($src);
    }
    
    if ($this->aborted)
      goto err;
    
    // ---------------------------------------
    // step 2: translate units
    
    foreach ($this->libs as $lib)
      $this->compile($lib);
    
    foreach ($this->srcs as $src)
      $this->compile($src); 
    
    if ($this->aborted)
      goto err;
    
    //goto out;
     
    // ---------------------------------------
    // step 3: pack sources into a phar
    
    $bnd = new Bundle($this);
    register_shutdown_function([ $bnd, 'cleanup' ]);
    
    foreach ($this->libs as $lib)
      $bnd->add_library($lib);
    
    foreach ($this->srcs as $src)
      $bnd->add_source($src);
    
    $bnd->deploy();
    
    out:
    Logger::debug('complete');
    return;
  
    err:
    Logger::error('compilation aborted due to previous error(s)');
  }
  
  /**
   * analyzes a unit
   *
   * @param  Source $src
   * @param  int    $kind
   */
  protected function analyze(Source $src)
  {        
    Logger::debug('analyze file %s', $src->get_path());
    
    if ($this->sroot === null)
      $this->sroot = $src->use_root();
    
    $src->set_root($this->sroot);
    
    foreach ($src->iter() as $file) {
      $unit = null;
       
      if (!$file->php) {
        $unit = $this->comp->analyze($file);
          
        if ($unit)
          $src->unit = $unit; 
      }      
    }
  }
  
  /**
   * compiles a source
   *
   * @param  Source $src
   */
  protected function compile(Source $src)
  {
    Logger::debug('compile file %s', $src->get_path());
    
    $this->comp->compile($src);
  }
}

