<?php

namespace phs;

require_once "source.php";
require_once "context.php";

require_once "parser.php";
require_once "analyzer.php";
require_once "generator.php";

use phs\ast\Unit;

class Compiler
{
  // context
  private $ctx;
  
  // sources to be compiled
  private $srcs;
  
  // parsed units
  private $units;
  
  // current state
  private $state;
  
  // compiler states
  const
    ST_WAITING = 1,
    ST_COMPILING = 2
  ;
  
  // components
  private $parser;
  private $analyzer;
  private $optimizer;
  private $generator;
  
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
    $this->srcs = [];
    $this->units = [];
    $this->state = self::ST_WAITING;
  }
  
  /**
   * add a source
   * 
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    if ($src instanceof FileSource) {
      $path = $src->get_name();
      
      if ($this->ctx->has_path($path))
        return false;
      
      $this->ctx->add_path($path);
    }
    
    $this->srcs[] = $src;
    
    if ($this->state === self::ST_COMPILING)
      // analyze it now
      $this->analyze($src, true);
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
  
  /**
   * compiler entry-point
   * 
   */
  public function compile()
  {    
    $this->state = self::ST_COMPILING;
    $this->parser = new Parser($this->ctx);
    $this->analyzer = new Analyzer($this->ctx, $this);
    // $this->optimizer = new Optimizer($this->ctx);
    $this->generator = new Generator($this->ctx);
    
    // 1. analyze
    foreach ($this->srcs as $src)
      $this->analyze($src);
    
    // on error: abort
    if (!$this->ctx->valid)
      return;
    
    /*
    // 2. optimize
    foreach ($this->units as $unit)
      $this->optimize($unit);
    
    // on error: abort
    if (!$this->ctx->valid)
      return;
    */
   
    // 3. emit code
    foreach ($this->units as $unit)
      $this->generate($unit);
  }
  
  /**
   * analyze source 
   * 
   * @param  Source  $src
   * @param  boolean $excl  exclusive
   */
  protected function analyze(Source $src, $excl = false)
  {
    if ($excl === true)
      // use an exclusive analyzer
      $anl = new Analyzer($this->ctx, $this);
    else
      // use the shared analyzer
      $anl = $this->analyzer;
    
    // parse source
    $unit = $this->parser->parse_source($src);
            
    if ($unit !== null) {
      $unit->dest = $src->get_dest();
        
      // analyze unit
      $anl->analyze($unit);
        
      // add it to the queue
      $this->add_unit($unit);
    }
  }
  
  /**
   * optimize unit
   * 
   * @param  Unit   $unit
   */
  protected function optimize(Unit $unit)
  {
    $this->optimizer->optimize($unit);
  }
  
  /**
   * generate target code
   * 
   * @param  Unit   $unit
   */
  protected function generate(Unit $unit)
  {
    $this->generator->generate($unit);
  }
}
