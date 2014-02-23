<?php

namespace phs;

/** reusable parser-base */
abstract class ParserBase
{
  // compiler context
  private $ctx;
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
  }
  
  /**
   * should return the current location 
   * 
   * @return Location
   */
  abstract public function loc();
  
  /**
   * main parsing function
   * 
   * @param  Lexer  $lex
   * @return Node
   */
  abstract public function parse(Lexer $lex);
  
  /**
   * parse a source
   * 
   * @param Source $src
   * @return Node
   */
  public function parse_source(Source $src)
  {
    $lex = new Lexer($this->ctx, $src);
    return $this->parse($lex);
  }
  
  /**
   * error handler
   * 
   */
  public function error()
  {
    $args = func_get_args();
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($this->loc(), COM_PSR, $lvl, $msg, $args);
  }
  
  /**
   * error handler with custom location
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_PSR, $lvl, $msg, $args);
  }
}
