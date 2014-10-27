<?php

namespace phs;

require_once 'utils.php';
require_once 'values.php';
require_once 'usage.php';

use phs\ast\Expr;
use phs\ast\Name;
use phs\ast\Ident;

// shortcut ... and it looks better :-)
use phs\ModuleScope as Module;

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
    #!dbg Logger::debug('lookup name `%s` (ns=%d)', name_to_str($node), $ns);
    
    $path = name_to_arr($node);
    $root = $node->root;
    
    if ($node->self) {
      $root = true; // "self::" is always fully qualified
      
      // get next best root-scope
      $scope = $this->scope;
      while (!($scope instanceof RootScope))
        $scope = $scope->prev;
      
      if ($scope instanceof ModuleScope)
        array_splice($path, 0, 0, $scope->path());
      // else -> just the current unit+global scope
    }
    
    // lookup path
    return $this->lookup_path($root, $path, $ns);
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
    #!dbg Logger::debug('lookup ident `%s` (ns=%d)', ident_to_str($node), $ns);
    
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
    #!dbg Logger::debug('lookup path `%s` (root=%s, ns=%d)', path_to_str($path), $root ? 'true' : 'false', $ns);
    
    // scopes must be defined
    assert($this->scope);
    assert($this->sunit);
    assert($this->sglob);
    
    $len = count($path);
    $ref = arr_to_path($path, $root);
    
    $imp = null; // usage (import)
    $mod = null; // module
    
    if ($root === false)
      $scope = $this->scope;
    else
      $scope = $this->sunit;
    
    if ($len === 1) {
      $sid = $path[0];
      $res = null;
      
      #!dbg Logger::debug('checking `%s` for a symbol named `%s`', $scope, $sid);
      
      $res = $scope->get($sid);
        
      if ($res->is_some() || // symbol found ...
          $res->is_priv() || // access to private symbol ... 
          $res->is_error())  // or some other error ...
        // early return here
        return $res;
    }
    
    $base = $path[0];
    
    #!dbg Logger::debug('checking root-scopes for a base-symbol named `%s`', $base);
    
    // walk scopes up and check each root-scope 
    for (; $scope; $scope = $scope->prev) {        
      // get nearest root-scope
      while (!($scope instanceof RootScope))
        $scope = $scope->prev;
      
      #!dbg Logger::debug('checking `%s` for a module named `%s`', $scope, $base);
        
      // check module-map
      if ($len > 1 && $scope->mmap->has($base)) {
        $mod = $scope->mmap->get($base);
        goto mod;
      }
      
      #!dbg Logger::debug('checking `%s` for a import named `%s`', $scope, $base);
      
      // check usage-map
      if ($scope->umap->has($base)) {
        $imp = $scope->umap->get($base);
        array_shift($path);
        goto imp;
      }
    }
     
    #!dbg Logger::debug('nothing found for `%s`', $base); 
      
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
    #!dbg Logger::debug('checking module `%s` for path `%s` (ns=%s)', $mod, path_to_str($path), $ns);
    
    $plen = count($path) - 1;
    $pidx = 1; // note: the first part of the path is the module itself
    $item = end($path);
    
    for (; $pidx < $plen; ++$pidx) {
      $pcur = $path[$pidx];
      
      #!dbg Logger::debug('checking module `%s` for a sub-module `%s`', $mod, $pcur);
      
      if (!$mod->mmap->has($pcur)) {    
        #!dbg Logger::debug('checking module `%s` for a import `%s`', $mod, $pcur);
            
        // sub-module not found, check public imports
        $imp = $mod->umap->get($pcur);
        
        if ($imp && $imp->pub)
          return $this->lookup_import($imp, array_slice($path, $pidx + 1), $ns);
        
        // not imported
        goto err;
      }
      
      #!dbg Logger::debug('fetching sub-module `%s` from `%s`', $pcur, $mod);
      
      $mod = $mod->mmap->get($pcur);
      if (!$mod) goto err;
    }
    
    #!dbg Logger::debug('checking module `%s` for a symbol named `%s`', $mod, $item);
    
    // return the requested symbol
    if ($mod->has($item, $ns)) {
      $sym = $mod->get($item, $ns);
      
      if ($sym->is_none())
        $sym->path = $path;
      
      return $sym;
    }
    
    #!dbg Logger::debug('checking module `%s` for a public import `%s`', $mod, $item);
    
    // lookup relative imports
    if ($mod->umap->has($item)) {
      $imp = $mod->umap->get($item);
      
      if ($imp->pub)
        return $this->lookup_import($imp, [], $ns);
    }
    
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
    // path to resolve
    $path = array_merge($imp->path, $path);
    
    // setup roots: imports are absolute by default
    $roots = [ $this->sunit, $this->sglob ];
    
    if ($imp->self)
      // qualify relative import
      array_splice($path, 0, 0, $imp->root->path());
    
    // helper
    $plen = count($path) - 1;
    $item = end($path);
    
    // resolve
    // TODO: make this algo iterative
    foreach ($roots as $root) {
      #!dbg Logger::debug('lookup import `%s` `%s`', $root, path_to_str($path));
      
      // resolve path from index 0 to n-1
      for ($pidx = 0; $pidx < $plen; ++$pidx) {
        $pcur = $path[$pidx];
        
        #!dbg Logger::debug('looking for `%s` in `%s`', $pcur, $root);
        
        if ($root->mmap->has($pcur)) {
          #!dbg Logger::debug('found a sub-module, switching scope');
          $root = $root->mmap->get($pcur);
        } else {
          if ($root->umap->has($pcur)) {
            $pimp = $root->umpa->get($pcur);
            
            if ($pimp && $pimp->pub) {
              #!dbg Logger::debug('found a public import, resolving from there');
              return $this->lookup_import($pimp, array_slice($path, $pidx + 1), $ns);
            }
          }
          
          #!dbg Logger::debug('nothing found, using next root');
          continue 2;
        }
      }
      
      #!dbg Logger::debug('looking for `%s` in `%s`', $item, $root);
      
      if ($root->has($item, $ns)) {
        #!dbg Logger::debug('symbol found!');
        
        $sym = $root->get($item, $ns);
        
        if ($sym->is_some())
          $imp->symbol = &$sym->unwrap();
        
        return $sym;
      }
      
      if ($root->umap->has($item)) {
        $rimp = $root->umap->get($item);
        
        #!dbg Logger::debug('found a import');
        
        if ($rimp->pub) {
          #!dbg Logger::debug('found a public import, resolving it');
          return $this->lookup_import($rimp, [], $ns);
        }
      }
      
      #!dbg Logger::debug('nothing found, using next root');
    }
    
    // no more roots -> bail out
    #!dbg Logger::debug('lookup failed');
    
    // return None
    return ScResult::None();
  }
}
