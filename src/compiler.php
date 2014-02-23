<?php

namespace phs;

require_once "source.php";
require_once "context.php";

class Compiler
{
  // context
  private $ctx;
  
  // sources to be compiled
  private $srcs;
  
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
    $this->srcs = [];
  }
  
  public function add(Source $src)
  {
    $this->srcs[] = $src;
  }
  
  public function compile()
  {    
    $units = [];
    
    // 1. parse units and analyze them
    require_once "parser.php";
    require_once "analyzer.php";
    
    $psr = new Parser($this->ctx);
    $anl = new Analyzer($this->ctx);
    
    foreach ($this->srcs as $src) {
      $unit = $psr->parse_source($src);
      $unit->dest = $src->get_dest();
      
      if ($unit !== null) {
        // analyze unit
        $anl->analyze($unit);
        
        // add it to the queue
        $units[] = $unit;
      }
    }
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // 2. resolve
    require_once "resolver.php";
    $rsv = new Resolver($this->ctx);
    
    foreach ($units as $unit)
      $rsv->resolve($unit);
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // 3. improve
    require_once "improver.php";
    $imp = new Improver($this->ctx);
    
    foreach ($units as $unit)
      $imp->improve($unit);
    
    // on error: stop
    if (!$this->ctx->valid) return;
    
    // 4. translate
    require_once "translator.php";
    $trs = new Translator($this->ctx);
    
    foreach ($units as $unit)
      $trs->translate($unit);
  }
}
