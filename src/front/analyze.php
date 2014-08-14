<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';

// analyzer components
require_once 'validate.php';
require_once 'desugar.php';
require_once 'collect.php';
require_once 'reduce.php';
require_once 'resolve.php';

use phs\Config;
use phs\Logger;
use phs\Session;
use phs\FileSource;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

/** analyzer */
class Analyzer
{
  // @var Session  session
  private $sess;
  
  // @var UnitScope
  private $scope;
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    //
    $this->sess = $sess;
  }
  
  /**
   * starts the analyzer
   * 
   * @param  Unit   $unit
   * @return UnitScope
   */
  public function analyze(Unit $unit)
  {    
    // 1. desugar unit
    $this->desugar_unit($unit);
    
    $this->scope = new UnitScope($unit);
     
    // 2. collect classes, interfaces and traits
    // 3. collect functions
    // 4. collect class, interface and trait-members
    $this->collect_unit($unit);
    
    if ($this->sess->aborted)
      goto err;
    
    // 5. reduce constant expressions
    // 6. resolve usage and imports
    $this->resolve_unit($unit);
    
    if ($this->sess->aborted)
      goto err;
    
    // 7. validate
    $this->validate_unit($unit);
    
    // 8. collect variables / branches
    // TODO:
    
    if ($this->sess->aborted)
      goto err;
    
    out:
    return $this->scope;
    
    err:
    unset ($this->scope);
    gc_collect_cycles();
    return null;
  }
  
  protected function validate_unit(Unit $unit)
  {
    $uval = new UnitValidator($this->sess);
    $uval->validate($unit);
  }
  
  /**
   * desugars the unit
   *
   * @param  Unit $unit
   * @return void
   */
  protected function desugar_unit(Unit $unit)
  {
    $desu = new UnitDesugarer($this->sess);
    $desu->desugar($unit);
  }
  
  /**
   * collects the unit-scope
   *
   * @param  Unit $unit
   * @return UnitScope
   */
  protected function collect_unit(Unit $unit)
  {
    $ucol = new UnitCollector($this->sess);
    $ucol->collect($this->scope, $unit);
  }
  
  /**
   * resolves the unit
   *
   * @param  Unit   $unit
   * @return void
   */
  protected function resolve_unit(Unit $unit)
  {
    $ures = new UnitResolver($this->sess);
    $ures->resolve($this->scope, $unit);
  }
  
  /**
   * resolves unit and module-usage (use-delcs)
   *
   * @return void
   */
  protected function resolve_usage()
  {
    $heap = new Dict;
    $heap->set('huhu', $unit);
    
    // absolute import directory
    $root = $this->sess->root;
    $udct = $this->sess->udct;
    $stdl = PHS_STDLIB;
    
    // resolve own usage
    foreach ($scope->umap as $use) {
      $path = implode('::', $use->path);
      $nstd = $use->path[0] !== 'std';
      
      if ($this->sess->udct->has($path)) {
        Logger::debug('import is already resolved (`%s`)', $path);
        Logger::debug('%s', $this->sess->udct->get($path));
        continue;
      }
            
      if (($nstd && $this->resolve_import($root, $path, $use)) ||
                    $this->resolve_import($stdl, $path, $use))
        Logger::debug('import `%s` resolved to a file', $path);
    }
    
    // resolve usage from sub-modules
    foreach ($scope->mmap as $sub)
      $this->resolve_usage($sub);
  }
  
  /**
   * tries to resolve a import
   *
   * @param  string $root
   * @param  string $hash
   * @param  Usage  $use 
   * @return boolean
   */
  protected function resolve_import($root, $hash, Usage $use)
  {    
    static $ds = DIRECTORY_SEPARATOR;
    
    $base = $root . $ds;
    $path = $use->path;
    $udct = $this->sess->udct;
    $find = [];
    
    // use foo::bar::baz;
    // 
    // -> foo/bar/baz.phs
    // -> foo/bar.phs
    // -> foo.phs
    
    for (; count($path); array_pop($path))
      $find[] = $base . implode($ds, $path) . '.phs';
    
    foreach ($find as $n => $file) {
      Logger::debug('try using %s [%d]', $file, $n + 1);
      
      if (is_file($file)) {
        // mark source as `use`
        $usrc = new FileSource($file);
        $usrc->origin = $use;
        
        // add it to the compiler-queue
        $this->sess->add_source($usrc);
        $udct->set($hash, $file);
        return true;
      }
    }
    
    $udct->set($hash, '<virtual>');
    return false;
  }
}
