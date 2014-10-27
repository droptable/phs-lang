<?php

namespace phs;

use phs\ast\Unit;

/** unit exporter */
class ExportTask implements Task
{
  // @var Session
  private $sess;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
  }
  
  /**
   * export
   *
   * @param  Unit   $unit
   */
  public function run(Unit $unit)
  { 
    // non-private symbols from the unit-scope are already exported.
    // this method exports the generated modules to the global-scope
    
    $dst = $this->sess->scope;
    $src = $unit->scope;
    
    // move public "usage" to the global-scope
    foreach ($src->umap as $imp)
      if ($imp->pub) {
        #!dbg Logger::debug('exporting public import %s as %s', path_to_str($imp->path), $imp->item);
        $dst->umap->add($imp);
      }
      
    // merge modules
    $stk = [[ $src->mmap, $dst ]];
    
    while (count($stk)) {
      list ($src, $dst) = array_pop($stk);
      
      foreach ($src as $mod) {
        
        #!dbg Logger::debug('exporting module %s (parent=%s)', $mod, $dst);
        
        if ($dst->mmap->has($mod->id))
          $map = $dst->mmap->get($mod->id);
        else {
          $dup = new ModuleScope($mod->id, $dst);
          $dst->mmap->add($dup);
          $map = $dup;
        }
                
        foreach ($mod->iter() as $sym) {
          #!dbg Logger::debug('exporting %s from module %s', $sym->id, $mod);
          $org = $sym->scope;
          $map->add($sym);
          // TODO: maybe abstract this behavior in a specialized scope?
          $sym->scope = $org;
        }
        
        // move public "usage" too
        foreach ($mod->umap as $imp)
          if ($imp->pub) {
            #!dbg Logger::debug('exporting public import %s as %s', path_to_str($imp->path), $imp->item);
            $map->umap->add($imp);
          }
                  
        // merge submodules
        array_push($stk, [ $mod->mmap, $map ]);
      }
    }
  }
}
