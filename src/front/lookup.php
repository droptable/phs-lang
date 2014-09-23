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
    #!dbg Logger::debug('lookup name %s (ns=%d)', name_to_str($node), $ns);
    
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
    #!dbg Logger::debug('lookup ident %s (ns=%d)', ident_to_str($node), $ns);
    
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
    #!dbg Logger::debug('lookup path %s (root=%s, ns=%d)', path_to_str($path), $root ? 'true' : 'false', $ns);
    
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
      
      #!dbg Logger::debug('checking scope %s for a symbol named %s', $scope, $sid);
      
      $res = $scope->get($sid);
        
      if ($res->is_some() || // symbol found ...
          $res->is_priv() || // access to private symbol ... 
          $res->is_error())  // or some other error ...
        // early return here
        return $res;
    }
    
    $base = $path[0];
    
    #!dbg Logger::debug('checking root-scopes for a base-symbol named %s', $base);
    
    // walk scopes up and check each root-scope 
    for (; $scope; $scope = $scope->prev) {        
      // get nearest root-scope
      while (!($scope instanceof RootScope))
        $scope = $scope->prev;
      
      #!dbg Logger::debug('checking scope %s for a module named %s', $scope, $base);
        
      // check module-map
      if ($scope->mmap->has($base)) {
        $mod = $scope->mmap->get($base);
        goto mod;
      }
      
      #!dbg Logger::debug('checking scope %s for a import named %s', $scope, $base);
      
      // check usage-map
      if ($scope->umap->has($base)) {
        $imp = $scope->umap->get($base);
        goto imp;
      }
    }
     
    #!dbg Logger::debug('nothing found for %s', $base); 
      
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
    #!dbg Logger::debug('checking module %s for path %s (ns=%s)', $mod, path_to_str($path), $ns);
    
    $plen = count($path) - 1;
    $pidx = 1; // note: the first part of the path is the module itself
    $item = end($path);
    
    for (; $pidx < $plen; ++$pidx) {
      $pcur = $path[$pidx];
      
      #!dbg Logger::debug('checking module %s for a import %s', $mod, $pcur);
      
      if (!$mod->mmap->has($pcur)) {        
        // sub-module not found, check public imports
        $imp = $mod->umap->get($pcur);
        
        if ($imp && $imp->pub) {
          Logger::warn('[bug] recursive aliases currently not well implemented');
          Logger::warn('[bug] current path is %s', path_to_str($mod->path()));
          Logger::warn('[bug] aliased path is %s (via %s)', path_to_str($imp->path), $pcur);
        
          return $this->lookup_path(true, array_merge(
            $mod->path(), // absolute path to current module
            $imp->path, // new path to resolve
            array_slice($path, $pidx + 1) // rest of the original path
          ), $ns);
        }
        
        // not imported
        goto err;
      }
      
      #!dbg Logger::debug('fetching sub-module %s from %s', $pcur, $mod);
      
      $mod = $mod->mmap->get($pcur);
      if (!$mod) goto err;
    }
    
    #!dbg Logger::debug('checking module %s for a symbol named %s', $mod, $item);
    
    // return the requested symbol
    $sym = $mod->get($item);
    
    if ($sym->is_none())
      $sym->path = $path;
    
    if ($sym->is_some() ||
        $sym->is_priv() ||
        $sym->is_error())
      return $sym;
        
    #!dbg Logger::debug('checking module %s for a public import %s', $mod, $item);
    
    // lookup relative imports
    return $this->lookup_relative($mod, $item, $ns);
    
    err:
    return ScResult::None();
  }
  
  /**
   * lookup a relative import from a module
   *
   * @param  Module  $mod
   * @param  string  $item
   * @param  integer $ns
   * @return ScResult
   */
  public function lookup_relative(Module $mod, $item, $ns = -1)
  {
    // check if the module has a used symbol
    $imp = $mod->umap->get($item);
    $org = $imp; // the actual used import
    
    imp:
    if ($imp && $imp->pub) {
      // module-relative aliases are subject to change.
      // instead a new keyword named "self" will be used in future versions
      // 
      // old:
      // 
      // module foo {
      //  fn bar(){}
      //  public use bar as baz; // relative
      // }
      // 
      // 
      // new:
      // 
      // module foo {
      //  fn bar(){}
      //  public use self::bar as baz; // absolute, <self> expands to "::foo"
      // }
      // 
      #!dbg Logger::debug('module %s has a public alias of %s called %s, using that now', $mod, $imp->orig, $imp->item);
      
      // resolve $imp->path relative to $mod
      $plen = count($imp->path) - 1;
      
      for ($pidx = 0; $pidx < $plen; ++$pidx) {
        $pcur = $imp->path[$pidx];
        
        #!dbg Logger::debug('checking %s part of alias %s', $pcur, path_to_str($imp->path));
        
        if ($mod->mmap->has($pcur)) {
          $mod = $mod->mmap->get($pcur);
          #!dbg Logger::debug('%s is an alias of an module, switching scope to', $pcur, $mod);
        } else {
          if ($mod->umap->has($pcur)) {
            $imp = $mod->umap->get($pcur);
            goto imp;
          } else
            goto err;
        }
      }
      
      // the alias could be an alias of an alias
      if ($mod->umap->has($imp->orig)) {
        $imp = $mod->umap->get($imp->orig);
        goto imp;
      }
      
      // return the symbol
      #!dbg Logger::debug('looking for %s in %s (ns=%d)', $imp->orig, $mod, $ns);
      $sym = $mod->get($imp->orig, $ns);
      
      if ($sym->is_some()) {
        $imp->symbol = &$sym->unwrap();
        $org->symbol = &$sym->unwrap();
      } else
        $sym->path = $imp->path;
      
      #!dbg Logger::debug('is private = %s', $sym->is_priv() ? 'yep' : 'nope');
            
      return $sym;
    }
    
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
    #!dbg Logger::debug('looking up imported symbol `%s` via `%s`', path_to_str($path), path_to_str($imp->path));
    
    $root = $this->scope;
    $base = $imp->path[0];
    
    #!dbg Logger::debug('base = %s', $base);
    
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
      
      #!dbg Logger::debug('checking %s for a module %s', $root, $base);
      
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
      
      #!dbg Logger::debug('checking scope %s for %s (plen=%d)', $root, $item, $plen);
      
      // check if imported name resolves to a symbol
      if ($plen === 0 && $imp->item === $item) {
        $sym = $root->get($item);
        break;
      }
                  
      // otherwise: imported name must be a module
      if (!$root->mmap->has($imp->orig))
        // use next root
        continue;
      
      #!dbg Logger::debug('found a possible root %s', $root);
      
      $root = $root->mmap->get($imp->orig);
      
      #!dbg Logger::debug('root is now %s', $root);
      
      // try to resolve the requested path
      // note: $pidx = 0 -> the current root
      for ($pidx = 1; $pidx < $plen; ++$pidx) {
        $pcur = $path[$pidx];
        
        #!dbg Logger::debug('checking for a sub-module %s in %s', $pcur, $root);
        
        if (!$root->mmap->has($pcur))
          // use next root
          continue 2;
        
        #!dbg Logger::debug('found sub-module %s in %s', $pcur, $root);
        $root = $root->mmap->get($pcur);
      }
      
      #!dbg Logger::debug('checking %s for %s', $root, $item);
      
      // resolve requested symbol
      if ($root->has($item)) {
        $sym = $root->get($item);
        break;
      }
      
      if ($root->umap->has($item)) {
        $sym = $this->lookup_relative($root, $item, $ns);
        break;
      }
    }
    
    if ($sym !== null) {
      if ($sym->is_some())
        $imp->symbol = &$sym->unwrap();
      
      return $sym;
    }
    
    #!dbg Logger::debug('lookup import failed');
    
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
