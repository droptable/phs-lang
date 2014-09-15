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
      
      for (;;) {        
        // get nearest root-scope
        while (!($scope instanceof RootScope))
          $scope = $scope->prev;
        
        if (!$scope || $scope instanceof UnitScope || 
                       $scope instanceof GlobScope) 
          break; // note: unit and global-scope gets checked later
        
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
        
        // try parent scope
        $scope = $scope->prev;
      }
    }
    
    // check unit-scope module-map
    if ($this->sunit->mmap->has($base)) {
      $mod = $this->sunit->mmap->get($base);
      goto mod;
    }
    
    // check unit-scope usage-map
    if ($this->sunit->umap->has($base)) {
      $imp = $this->sunit->umap->get($base);
      goto imp;
    }
    
    // check global-scope module-map
    if ($this->sglob->mmap->has($base)) {
      $mod = $this->sglob->mmap->get($base);
      goto mod;
    }
    
    // note: global scope does not have a "usage-map"
    // ... to be honest: it does, but its unused
    
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
    
    for (; $pidx < $plen; ++$pidx) {
      $pcur = $path[$pidx];
      
      if (!$mod->mmap->has($pcur))
        // sub-module not found, early return here
        return ScResult::None();
      
      $mod = $mod->mmap->get($pcur);
    }
    
    // return the requested symbol
    return $mod->get($path[$plen]);
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
    
    for (; $root; $root = $root->prev) {
      // walk up to the next root-scope
      while (!($root instanceof RootScope)) {
        // if no prev-scope: current root is the global-scope
        if (!$root->prev) break 2;
        
        // use prev scope
        $root = $root->prev;
      }
      
      if ($root instanceof UnitScope) {
        // break if we're already looked in the current unit
        if ($unit === true) break;
        
        // don't look in the current unit again next time
        $unit = true;
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
      if ($plen === 0 && $imp->item === $item)
        return $root->get($item);
      
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
      if ($root->has($item))
        return $root->get($item);
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
