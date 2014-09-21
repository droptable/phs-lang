<?php

namespace phs\front;

require_once 'utils.php';
require_once 'values.php';
require_once 'usage.php';

use phs\Logger;

use phs\front\ast\Expr;
use phs\front\ast\Name;
use phs\front\ast\Ident;

// shortcut ... and it looks better :-)
use phs\front\ModuleScope as Module;

/** name/value lookup */
trait Lookup
{  
  // @var Scope  current scope
  private $scope;
    
  // @var UnitScope
  private $sunit;
  
  // @var GlobScope
  private $sglob;
  
  /**
   * lookup a name
   *
   * @param  Node $node
   * @param  int  $ns
   * @return ScResult
   */
  public function lookup_name($node, $ns = -1)
  {
    // lookup path
    $path = name_to_arr($node);
    return $this->lookup_path($node->root, $path, $ns);
  }
  
  /**
   * lookup a ident
   *
   * @param  Node $node
   * @param  int  $ns
   * @return ScResult
   */
  public function lookup_ident($node, $ns = -1)
  {
    // lookup path
    $path = [ ident_to_str($node) ];
    return $this->lookup_path(false, $path, $ns);
  }
  
  /**
   * lookup a path
   *
   * @param  boolean $root
   * @param  array $path
   * @param  int   $ns
   * @return ScResult
   */
  public function lookup_path($root, $path, $ns = -1)
  {
    // scopes must be defined
    assert($this->scope);
    assert($this->sunit);
    assert($this->sglob);
    
    $len = count($path);
    $ref = arr_to_path($path, $root);
    
    $imp = null; // usage (import)
    $mod = null; // module
    
    if ($len === 1) {
      $sid = $path[0];
      $res = null;
            
      if ($root === false)
        $scope = $this->scope;
      else
        $scope = $this->sunit;
      
      $res = $scope->get($sid);
        
      if ($res->is_some() || // symbol found ...
          $res->is_priv() || // access to private symbol ... 
          $res->is_error())  // or some other error ...
        // early return here
        return $res;
    }
    
    $base = $path[0];
    
    // walk scopes up and check each root-scope 
    if ($root === false) {
      $scope = $this->scope;
      
      for (; $scope; $scope = $scope->prev) {        
        // get nearest root-scope
        while (!($scope instanceof RootScope))
          $scope = $scope->prev;
                
        // check module-map
        if ($scope->mmap->has($base)) {
          $mod = $scope->mmap->get($base);
          goto mod;
        }
        
        // check usage-map
        if ($scope->umap->has($base)) {
          $imp = $scope->umap->get($base);
          goto imp;
        }
      }
    }
       
    // no jump? no symbol was found
    return ScResult::None();
    
    // imported symbol
    imp:
    return $this->lookup_import($imp, $path, $ns);
    
    // module symbol
    mod:
    return $this->lookup_module($mod, $path, $ns);
  }  
  
  /**
   * lookup a path inside a module
   *
   * @param  ModuleScope $mod
   * @param  array       $path
   * @param  integer     $ns
   * @return ScResult
   */
  public function lookup_module(Module $mod, array $path, $ns = -1)
  {
    $plen = count($path) - 1;
    $pidx = 1; // note: the first part of the path is the module itself
    $item = end($path);
    
    for (; $pidx < $plen; ++$pidx) {
      $pcur = $path[$pidx];
      
      if (!$mod->mmap->has($pcur)) {
        // sub-module not found, check public imports
        $imp = $mod->umap->get($pcur);
        
        if ($imp && $imp->pub)
          return $this->lookup_import($imp, array_slice($path, $pidx), $ns);
        
        // not imported
        goto err;
      }
      
      $mod = $mod->mmap->get($pcur);
    }
    
    // return the requested symbol
    $sym = $mod->get($item);
    
    if ($sym->is_some() ||
        $sym->is_priv() ||
        $sym->is_error())
      return $sym;
        
    // check if the module has a used symbol
    $imp = $mod->umap->get($item);
    
    Logger::debug('%s::%s', path_to_str($mod->path()), $item);
    var_dump($mod->umap);
    
    if ($imp && $imp->pub)
      return $this->lookup_import($imp, [ $item ], $ns);
    
    // otherwise: bail out
    err:
    return ScResult::None();
  }
  
  /**
   * lookup a imported symbol
   *
   * @param  Usage   $imp
   * @param  array   $path
   * @param  integer $ns
   * @return ScResult
   */
  public function lookup_import(Usage $imp, array $path, $ns = -1)
  {
    Logger::debug('looking up imported symbol `%s` via `%s`', 
      path_to_str($path), path_to_str($imp->path));
    
    $root = $this->scope;
    $base = $imp->path[0];
    
    // note the last item can be a module or a symbol,
    // therefore we have to check both (scope and module-map) 
    // if the length of the requested path is 1
    $ilen = count($imp->path) - 1;
    
    // note: last item of the given path is the requested symbol
    $plen = count($path) - 1;
    $item = end($path);
    
    // avoid double-lookup in the current unit ...
    // this can lead to a infinite loop
    $unit = false;
    $glob = false;
    
    // resolved symbol
    $sym = null;
    
    for (; $root; $root = $root->prev) {
      // walk up to the next root-scope
      while (!($root instanceof RootScope)) {
        // if no prev-scope: current root is the global-scope
        if (!$root->prev) break 2;
        
        // use prev scope
        $root = $root->prev;
      }
      
      if ($root instanceof UnitScope) {
        // we're already looked in the current unit,
        // switch to the global scope then
        if ($unit === true)
          $root = $this->sglob;
        
        $unit = true;
      }
      
      if ($root instanceof GlobScope) {
        if ($glob === true)
          break;
        
        $glob = true;
      }
            
      // check if current root has a module named $base
      if (!$root->mmap->has($base))
        // use next root
        continue;
            
      // try to resolve the imported name
      for ($iidx = 0; $iidx < $ilen; ++$iidx) {
        $icur = $imp->path[$iidx];
        
        if (!$root->mmap->has($icur))
          // use next root
          continue 2; 
        
        $root = $root->mmap->get($icur);
      }
      
      // check if imported name resolves to a symbol
      if ($plen === 0 && $imp->item === $item) {
        $sym = $root->get($item);
        break;
      }
            
      // otherwise: imported name must be a module
      if (!$root->mmap->has($imp->orig))
        // use next root
        continue;
      
      $root = $root->mmap->get($imp->orig);
      
      // try to resolve the requested path
      // note: $pidx = 0 -> the current root
      for ($pidx = 1; $pidx < $plen; ++$pidx) {
        $pcur = $path[$pidx];
        
        if (!$root->mmap->has($pcur))
          // use next root
          continue 2;
        
        $root = $root->mmap->get($pcur);
      }
      
      // resolve requested symbol
      if ($root->has($item)) {
        $sym = $root->get($item);
        break;
      }
    }
    
    if ($sym !== null) {
      $imp->symbol = $sym;
      return $sym;
    }
    
    // no more roots ... lookup failed
    return ScResult::None();
  }
  
  /**
   * lookup a member
   *
   * @param  ClassSymbol|TraitSymbol  $sym
   * @param  string  $id 
   * @param  integer $ns
   * @return ScResult
   */
  public function lookup_member($sym, $id, $ns = -1)
  {
    for (;;) {    
      $mem = $sym->members;
      
      if ($mem->has($id, $ns))
        return $mem->get($id, $ns);
      
      if (!($sym instanceof ClassSymbol) || !$sym->super)
        break;
              
      $sym = $sym->super->symbol;
    }
    
    return ScResult::None();
  }
}
