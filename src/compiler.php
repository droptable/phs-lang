<?php

namespace phs;

require_once "source.php";
require_once "context.php";

use phs\ast\Unit;

class Compiler
{
  // context
  private $ctx;
  
  // sources to be compiled
  private $srcs;
  
  // parsed units
  private $units;
  
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
    $this->srcs = [];
    $this->units = [];
  }
  
  /**
   * add a source
   * 
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    $this->srcs[] = $src;
  }
  
  /**
   * add a parsed unit (ast)
   * 
   * @param Unit $unit
   */
  public function add_unit(Unit $unit)
  {
    $this->units[] = $unit;
  }
  
  public function compile()
  {    
    // 1. parse units and analyze them
    require_once "parser.php";
    require_once "analyzer.php";
    
    $psr = new Parser($this->ctx);
    $anl = new Analyzer($this->ctx, $this);
    
    foreach ($this->srcs as $src) {
      $unit = $psr->parse_source($src);
            
      if ($unit !== null) {
        $unit->dest = $src->get_dest();
        
        // analyze unit
        $anl->analyze($unit);
        
        // add it to the queue
        $this->units[] = $unit;
      }
    }
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // on this stage, the analyzer could have added more units
    
    return;
    
    // 2. resolve
    require_once "resolver.php";
    $rsv = new Resolver($this->ctx);
    
    foreach ($this->units as $unit)
      $rsv->resolve($unit);
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // 3. improve
    require_once "improver.php";
    $imp = new Improver($this->ctx);
    
    foreach ($this->units as $unit)
      $imp->improve($unit);
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // 4. translate
    require_once "translator.php";
    $trs = new Translator($this->ctx);
    
    foreach ($this->units as $unit)
      $trs->translate($unit);
  }
}
