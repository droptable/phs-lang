<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';
require_once 'collect.php';

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
    // 1. collect classes, interfaces and traits
    // 2. collect functions
    // 3. collect usage
    // 4. collect class, interface and trait-members
    $scope = $this->collect_unit($unit);
    
    //$this->collect_members($scope);  
    
    return $scope;
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
    return $ucol->collect($unit);
  }
  
  /**
   * resolves unit and module-usage (use-delcs)
   *
   * @param  UnitScope $scope
   * @return void
   */
  protected function resolve_usage(RootScope $scope)
  {
    // absolute import directory
    $root = $this->sess->root;
    
    // resolve own usage
    foreach ($scope->umap as $use) {
      $path = implode('::', $use->path);
      
      if ($this->sess->udct->has($path)) {
        Logger::debug('import is already resolved (`%s`)', $path);
        Logger::debug('%s', $this->sess->udct->get($path));
        continue;
      }
            
      if (!$this->resolve_import($root, $path, $use) &&
          !$this->resolve_import(PHS_STDLIB, $path, $use))
        Logger::error('unable to resolve `%s` to a file', $path);
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
    $find = [];
    
    // use foo::bar::baz;
    // 
    // -> foo/bar/baz.phm
    // -> foo/bar.phm
    // -> foo.phm
    
    for (; count($path); array_pop($path))
      $find[] = $base . implode($ds, $path) . '.phm';
    
    foreach ($find as $n => $file) {
      Logger::debug('try using %s [%d]', $file, $n + 1);
      
      if (is_file($file)) {
        $this->sess->add_source(new FileSource($file));
        $this->sess->udct->set($hash, $file);
        return true;
      }
    }
    
    $this->sess->udct->set($hash, 'not resolved');
    return false;
  }
}

