<?php

namespace phs;

const 
  COM_GLB = 1, // global (no specific context)
  COM_LEX = 2, // lexer context
  COM_PSR = 3, // parser context
  COM_WLK = 4, // walker context
  COM_ANL = 5, // analyze context
  COM_RSV = 6, // resolve context
  COM_TRS = 7, // translate context
  COM_RDC = 8, // reduce context
  COM_IMP = 9, // improve context
  COM_VLD = 10 // validate context
;

const 
  ERR_INFO = 1,
  ERR_WARN = 2,
  ERR_ERROR = 3,
  ERR_ABORT = 4
;

class Context
{
  // abort-flag
  public $abort = false;
  
  // each compilation-step checks this flag and as long the value 
  // stays `true` the compilation continues
  public $valid = true;
  
  // options
  private $opts = [];
  
  // global scope
  private $scope;
  
  // root modules
  private $module;
  
  /**
   * returns the global scope
   * 
   * @return Scope
   */
  public function get_scope()
  {
    if (!$this->scope) {
      require_once 'scope.php';
      $this->scope = new Scope;
    }
    
    return $this->scope;
  }
  
  /**
   * sets the global scope
   * 
   * @param Scope $scope
   */
  public function set_scope(Scope $scope)
  {
    $this->scope = $scope;
  }
  
  /**
   * returns the global module
   * 
   * @return Module
   */
  public function get_module()
  {
    if (!$this->module) {
      require_once 'module.php';
      $this->module = new Module('<root>');
      $this->module->root = true;
    }
    
    return $this->module;
  }
  
  /**
   * sets the global module
   * 
   * @param Module $mod
   */
  public function set_module(Module $mod)
  {
    $this->module = $mod;
  }
  
  /**
   * set a option
   * 
   * @param  string $name the option
   * @param  mixed $value the option value
   */
  public function set_option($n, $v)
  {
    $this->opts[$n] = $v;
  }
  
  /**
   * return a option
   * 
   * @param  string $name the option
   * @param  mixed $fallback a fallback
   * @return mixed
   */
  public function get_option($n, $f = null)
  {
    return isset ($this->opts[$n]) ? $this->opts[$n] : $f;
  }
  
  /**
   * trigger an error
   * 
   * @param int $component
   * @param int $error_type
   * @param string $message
   * @param mixed ... $arguments
   */
  public function error()
  {
    $args = func_get_args();
    $send = [];
    
    // location
    $send[] = null;
    // component
    $send[] = array_shift($args);
    // error-type
    $send[] = array_shift($args);
    // message
    $send[] = array_shift($args);
    // ...args
    $send[] = $args;
    
    call_user_func_array([ $this, 'verror_at' ], $send);
  }
  
  /**
   * trigger an error at a specific location
   * 
   * @param Location $location
   * @param int $component
   * @param int $error_type
   * @param string $message
   * @param mixed ... $arguments
   */
  public function error_at()
  {
    $args = func_get_args();
    $send = [];
    
    // location
    $send[] = array_shift($args);
    // component
    $send[] = array_shift($args);
    // error-type
    $send[] = array_shift($args);
    // message
    $send[] = array_shift($args);
    // ...args
    $send[] = $args;
    
    call_user_func_array([ $this, 'verror_at' ], $send);
  }
  
  /**
   * trigger an error (varidc)
   * 
   * @param int $component
   * @param int $error_type
   * @param string $message
   * @param array $arguments
   */
  public function verror()
  {
    $send = func_get_args();
    
    // location
    array_unshift($send, null);
    
    call_user_func_array([ $this, 'verror_at' ], $send);
  }
  
  /**
   * trigger an error at a specific location (varidc)
   * 
   * @param Location $location
   * @param int $component
   * @param int $error_type
   * @param string $message
   * @param array $arguments
   */
  public function verror_at()
  {
    $args = func_get_args();
    list ($loc, $com, $err, $msg, $fmt) = $args;
    
    if ($err === ERR_ABORT) {
      $this->abort = true;
      $err = ERR_ERROR;
    }
    
    if ($err === ERR_ERROR)
      $this->valid = false;
          
    $log = '';
    
    switch ($err) {
      case ERR_INFO:
        $log .= '[info] ';
        break;
      case ERR_WARN:
        $log .= '[warn] ';
        break;
      default:
      case ERR_ERROR:
        $log .= '[error]';
        break;
    }
    
    switch ($com) {
      default:
      case COM_GLB: $log .= ' glb'; break;
      case COM_LEX: $log .= ' lex'; break;
      case COM_PSR: $log .= ' prs'; break;
      case COM_WLK: $log .= ' wlk'; break;
      case COM_ANL: $log .= ' anl'; break;
      case COM_RSV: $log .= ' rsv'; break;
      case COM_TRS: $log .= ' trs'; break;
      case COM_RDC: $log .= ' rdc'; break;
      case COM_IMP: $log .= ' imp'; break;
      case COM_VLD: $log .= ' vld'; break;
    }
    
    $log .= ': ';
    
    if ($loc !== null) {
      assert($loc instanceof Location);
      $log .= "{$loc->file}:{$loc->pos->line}:{$loc->pos->coln}: ";
    }
    
    $log .= $this->format($msg, $fmt);
    fwrite(STDERR, "$log\n");
  }
  
  /**
   * formats a message
   * 
   * @param string $message
   * @param array $arguments
   * @return string
   */
  private function format($msg, $args)
  {
    if (empty($args))
      return $msg;
    
    $r = '';
    
    for ($f = 0, $i = 0, $l = strlen($msg); $i < $l; ++$i) {
      $c = $msg[$i];
      
      if ($c === '%') {
        $t = '';
        $s = 0;
        for ($o = $i + 1; $o < $l; ++$o, ++$s) {
          $n = $msg[$o];
          if (!ctype_alpha($n)) break;
          $t .= $n;
        }
        
        if (!empty($t)) {
          switch ($t) {
            case 'node':
            case 'symbol':
              assert(!empty($args));
              $i += $s;
              $a = array_splice($args, $f, 1)[0];
              $d = $this->{"format_$t"}($a);
              
              // deny "%..." results
              if (preg_match('/(?<![%])[%](?![%])/', $d))
                exit('a format-handler must not return format-able strings!'
                   . 'result was: ' . $d);
              
              $r .= $d;
              continue 2;
          }
        }
        
        ++$f;
      }
      
      $r .= $c;
    }
    
    return vsprintf($r, $args);
  }
  
  // TODO:
  private function format_node()
  {
    return 'node';
  }
  
  // TODO:
  private function format_symbol()
  {
    return 'symbol';
  }
}
