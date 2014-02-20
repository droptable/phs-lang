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
   * parse a string (text)
   * 
   * @param string $text
   * @param string $file
   * @return Node
   */
  public function parse_text($text, $file = '<unknown source>')
  {
    $lex = new Lexer($this->ctx, $text, $file);
    return $this->parse($lex);
  }
  
  /**
   * parse a file
   * 
   * @param string $file
   * @return Node
   */
  public function parse_file($file)
  {
    $path = realpath($file);
    
    if (empty($path)) {
      $this->error(ERR_ERROR, 'file %s not found', $file);
      return null;
    }
    
    if (!is_readable($path)) {
      $this->error(ERR_ERROR, '%s: permission denied', $file);
      return null;  
    }
    
    $text = file_get_contents($path);
    return $this->parse_text($text, $path);
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
