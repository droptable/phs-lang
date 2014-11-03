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
  KIND_SRC = 1,
  KIND_LIB = 2
;

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
  
  // @var int  current kind of file
  private $kind;
  
  // @var string  source root-directory (used to pack)
  private $sroot;
  
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
  public function __construct(Config $conf, $root)
  {    
    $this->conf = $conf;
    $this->rpath = $root;
    $this->abort = false;
    
    $this->scope = new GlobScope; // global scope
    $this->units = new Set;
    
    $this->srcs = new SourceSet;
    $this->libs = new SourceSet;
    
    // process-queue
    $this->queue = [];
    
    if (!$this->conf->get('nostd', false))
      $this->setup_std();
    
    $this->comp = new Compiler($this);
  }
  
  /**
   * pre-defines some built-in symbols of the stdlib and runtime
   *
   */
  protected function setup_std()
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
      
      if ($this->started)
        // in compilation: source may be added from a
        // require-declaration -> compile it now
        $this->analyze($src, KIND_SRC);
      else
        // add file to the compile-queue
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
    if ($src->check() && $this->libs->add($src)) {
      if ($this->started)
        // in compilation: source may be added from a
        // require-declaration -> compile it now
        $this->analyze($src, KIND_LIB);
      else
        // add file to the compile-queue
        $this->queue[] = [ $src, KIND_LIB ];
    }
  }
  
  /**
   * adds a imported source
   * this function is normally just called by the compiler (resolve.php)
   *
   * @param Source $src
   */
  public function add_import(Source $src)
  {
    $src->import = true;
    
    if ($this->kind === KIND_SRC)
      $this->add_source($src);
    else
      $this->add_library($src);
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
    
    // ---------------------------------------
    // step 1: analyze sources 
    
    while ($src = array_shift($this->queue))
      $this->analyze($src[0], $src[1]);
                   
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
    
    foreach ($this->libs as $lib)
      $bnd->add_library($lib);
    
    foreach ($this->srcs as $src)
      $bnd->add_source($src);
    
    $bnd->deploy();
    $bnd->cleanup();
    
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
  protected function analyze(Source $src, $kind)
  {    
    // new kind -> update source-root for follow-ups in add_import()
    if ($this->kind !== $kind)
      $this->sroot = $src->use_root();
    
    // update source-root
    $src->set_root($this->sroot);
    
    $root = $src->get_root();
    $path = $src->get_path();
    
    if (strpos($path, $root) !== 0) {
      $loc = $src->loc;
      Logger::error_at($loc, 'unable to import %s', $path);
      Logger::error_at($loc, 'current root-path is %s', $root); 
      Logger::info('you either have to copy this file into the shown \\');
      Logger::info('root-path or add it as a library which \\');
      Logger::info('gets included automatically for you');     
    } else {
      Logger::debug('analyze file %s', $src->get_path());
      
      $this->kind = $kind;
      
      foreach ($src->iter() as $file) {
        $unit = null;
        
        if (!$file->php) {
          $unit = $this->comp->analyze($file);
          
          if ($unit)
            $src->unit = $unit; 
        }      
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

