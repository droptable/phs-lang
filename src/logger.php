<?php

namespace phs;

require_once 'config.php';

const
  LOG_LEVEL_ALL     = 1,
  LOG_LEVEL_DEBUG   = 2, // debug messages
  LOG_LEVEL_VERBOSE = 3, // verbose
  LOG_LEVEL_INFO    = 4, // informations
  LOG_LEVEL_WARNING = 5, // warnings
  LOG_LEVEL_ERROR   = 6  // errors
;

class Logger
{
  // @var boolean
  private static $ansi = false;
  
  // @var boolean 
  private static $ostd = true;
  
  // @var string root-path (gets stripped from locations)
  private static $root;
  
  // @var int
  private static $level = LOG_LEVEL_ALL;
  
  // @var string|stream
  private static $dest = STDERR;
  
  // @var bool  continue-flag
  private static $cont = false;
  
  // @var int   continuation-width
  private static $clen = 0;
  
  // @var string  continuation-output
  private static $cout;
  
  // @var bool bail-out flag
  public static $bail = false;
  
  // @var bool log time
  private static $time = false;
  
  // @var int  line-width (length)
  private static $width = 80;
  
  // @var float
  public static $msec;
  
  // log-level hooks
  private static $hooks = [
    LOG_LEVEL_DEBUG => [],
    LOG_LEVEL_VERBOSE => [],
    LOG_LEVEL_INFO => [],
    LOG_LEVEL_WARNING => [],
    LOG_LEVEL_ERROR => []
  ];
  
  /* ------------------------------------ */
  
  /**
   * initializes the logger
   * @param  Config $conf
   * @return void
   */
  public static function init(Config $conf)
  {
    self::$root = getcwd();
    self::$ansi = $conf->log_ansi ?: 
      DIRECTORY_SEPARATOR !== '\\' || !!getenv('ANSICON');
    
    self::set_dest($conf->log_dest);
    self::set_level($conf->log_level);
    
    if ($conf->log_time === true) {
      self::$time = true;
      self::$msec = microtime(true);
    }
    
    // 250 -> max, 50 -> min 
    self::$width = max(50, min(250, $conf->log_width));
    
    Logger::debug('logger initialized');
  }
  
  /* ------------------------------------ */
  
  /**
   * adds a hook for a specific log-level
   * @param  int   $lvl 
   * @param  callable $hook
   * @return void
   */
  public static function hook($lvl, callable $hook)
  {
    if ($lvl < 0 || $lvl > LOG_LEVEL_ERROR)
      $lvl = LOG_LEVEL_ERROR;
    
    array_push(self::$hooks[$lvl], $hook);
  }
  
  /* ------------------------------------ */
  
  /**
   * sets the log output destination
   *
   * @param string|stream $dest
   */
  public static function set_dest($dest)
  {
    if ($dest === 'stderr')
      $dest = STDERR;
    elseif ($dest === 'stdout')
      $dest = STDOUT;
    elseif (!is_resource($dest)) {
      $dest = fopen($dest, 'w+');
      
      if ($dest)
        register_shutdown_function(function() use(&$dest) {
          fclose($dest);
        });
      else {
        echo "\n\n!!! cannot set logger-destination",
          "\n!!! using stderr instead\n\n";
        $dest = STDERR;
      }
    }
    
    self::$dest = $dest;
    self::$ostd = $dest === STDERR || $dest === STDOUT;
  }
  
  /**
   * returns the current log output desitnation
   *
   * @return stream
   */
  public static function get_dest()
  {
    return self::$dest;
  }
  
  /* ------------------------------------ */
  
  /**
   * sets the output log-level.
   * only messages with this level (or higher) will 
   * generate output / invoke hooks.
   *
   * @param int $lvl
   */
  public static function set_level($lvl)
  {
    if ($lvl < 0 || $lvl > LOG_LEVEL_ERROR)
      $lvl = LOG_LEVEL_DEBUG;
    
    self::$level = $lvl;
  }
  
  /**
   * returns the current lgo-level
   *
   * @return int
   */
  public static function get_level()
  {
    return self::$level;
  }
  
  /* ------------------------------------ */
  
  private static function intersect_root($path)
  {
    $root = self::$root;
    
    for ($i = 0, $last = ''; 
         $root && $last !== $root; 
         $last = $root, $root = dirname($root), ++$i) {
      $pos = strpos($path, $root);
      
      if ($pos === 0) 
        return str_repeat('..' . DIRECTORY_SEPARATOR, $i) 
          . substr($path, strlen($root) + 1); 
    }
    
    return $path;
  }
  
  /* ------------------------------------ */
  
  public static function assert($cond, $msg)
  {
    if (!$cond) {
      $fmt = [];
      for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
        array_push($fmt, func_get_arg($i));
      
      self::vlog_at(null, LOG_LEVEL_ERROR, $msg, $fmt);
      assert(0);
    }
  }
  
  public static function assert_at(Location $loc, $cond, $msg)
  {
    if (!$cond) {
      $fmt = [];
      for ($i = 3, $l = func_num_args(); $i < $l; ++$i)
        array_push($fmt, func_get_arg($i));
      
      self::vlog_at($loc, LOG_LEVEL_ERROR, $msg, $fmt);
      assert(0);
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * generic log method
   *
   * @param  int $lvl log-level
   * @param  string $msg
   * @param  mixed ...
   * @return void
   */
  public static function log($lvl, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, $lvl, $msg, $fmt);
  }
  
  /**
   * generic log method with location
   *
   * @param  Location $loc
   * @param  int   $lvl
   * @param  string   $msg
   * @param  mixed ...
   * @return void
   */
  public static function log_at(Location $loc, $lvl, $msg)
  {
    $fmt = [];
    for ($i = 3, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, $lvl, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  private static function _wrap($test, $pre, $text, $post)
  {
    if ($test) $text = "$pre$text$post";
    return $text;
  }
  
  /**
   * generic log method with variadic arguments support
   *
   * @param  Location $loc
   * @param  int $lvl
   * @param  string $msg
   * @param  array $fmt
   * @return void
   */
  public static function vlog_at(Location $loc = null, $lvl, $msg, array $fmt = [])
  {
    if ($lvl < 0 || $lvl > LOG_LEVEL_ERROR)
      $lvl = LOG_LEVEL_DEBUG;
    
    $text = trim($msg);
    $cont = substr($text, -1, 1) === '\\';
    
    if ($cont)
      $text = substr($text, 0, -1);
        
    $text = vsprintf($text, $fmt);
    
    foreach (self::$hooks[$lvl] as $cb)
      $cb($loc, $lvl, $text);
    
    if (self::$level > $lvl)
      return;
    
    $out = '';
    $std = self::$ostd && self::$ansi;    
    
    if (!self::$cont) {
      if (self::$time)
        $out .= sprintf(' +% 6.3fs ', microtime(true) - self::$msec);
      
      switch ($lvl) {
        case LOG_LEVEL_DEBUG:
          $out .= self::_wrap($std, "\033[1;32m", "[dbg]   ", "\033[0m");
          break;
        case LOG_LEVEL_INFO:
          $out .= self::_wrap($std, "\033[1;36m", "[info]  ", "\033[0m");
          break;
        case LOG_LEVEL_WARNING:
          $out .= self::_wrap($std, "\033[1;33m", "[warn]  ", "\033[0m");
          break;
        case LOG_LEVEL_ERROR:
          $out .= self::_wrap($std, "\033[1;31m", "[error] ", "\033[0m");
          break;
      }
      
      if ($loc !== null) {
        $src = $loc->file;
        $src = self::intersect_root($src);
        
        $out .= self::_wrap($std, "\033[1;37m", 
          "{$src}:{$loc->pos->line}:{$loc->pos->coln}: ", "\033[0m");
      }
    }
    
    $skip = self::$cont;    
    self::$cont = $cont;
    
    $text = wordwrap($text, self::$width);
    $loop = false;
    $wrap = explode("\n", $text);
    $last = array_pop($wrap);
    $size = count($wrap);
        
    if ($skip) {
      $loop = true;
      fwrite(self::$dest, $out);
      
      if ($size > 0)
        $chnk = array_shift($wrap);
      else {
        $chnk = $last;
        $size = 0;
        $last = null;
      }
      
      // split if necessary
      $slen = strlen($chnk);
      $bits = [];
      $smax = self::$width - self::$clen;
      
      #echo "\n\n$slen <-> $smax\n\n";
      
      
      if ($slen > $smax) {
        $sbeg = substr($chnk, 0, $smax);
        // go back to the last whitespace char like wordwrap()
        for ($i = $smax - 1; $i >= 0; $i--)
          if (ctype_space($sbeg[$i]))
            break;
        
        $sbeg = substr($sbeg, 0, $i);
        $chnk = substr($chnk, $i + 1);
        
        $bits[] = $sbeg;
      }
      
      $bits[] = $chnk;
      
      #var_dump($bits);
      #echo "\n\n";
      
      fwrite(self::$dest, $bits[0]);
      
      if (count($bits) > 1) {
        $cout = self::$cout;
        fwrite(self::$dest, "\n{$cout}... {$bits[1]}");
        self::$clen = strlen($bits[1]);
      } else 
        self::$clen += strlen($bits[0]);
    }
    
    if ($size > 0) {      
      foreach ($wrap as $chnk) {
        if ($loop) $chnk = "... $chnk";
        $loop = true;      
        fwrite(self::$dest, "\n{$out}$chnk");
      }
      
      // for the last segment
      $last = "... $last";
    }
    
    if (!$skip)
      fwrite(self::$dest, "\n");
    
    if ($last !== null) {
      self::$clen = strlen($last);
      self::$cout = $out;
      fwrite(self::$dest, "$out$last");
    }
    
    if (self::$bail && $lvl === LOG_LEVEL_ERROR && !self::$cont) {
      echo "\n\n";
      debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
      exit;
    }
  }
  
  /* ------------------------------------ */
  
  // @see Logger#vlog_at() --- LOG_LEVEL_DEBUG
  public static function debug($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_DEBUG, $msg, $fmt);
  }
  
  // @see Logger#vlog_at() --- LOG_LEVEL_DEBUG
  public static function debug_at(Location $loc = null, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_DEBUG, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  // @see Logger#vlog_at() --- LOG_LEVEL_VERBOSE
  public static function verbose($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_VERBOSE, $msg, $fmt);
  }
  
  // @see Logger#vlog_at() --- LOG_LEVEL_VERBOSE
  public static function verbose_at(Location $loc = null, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_VERBOSE, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  // @see Logger#vlog_at() --- LOG_LEVEL_INFO
  public static function info($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_INFO, $msg, $fmt);
  }
  
  // @see Logger#vlog_at() --- LOG_LEVEL_INFO
  public static function info_at(Location $loc = null, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_INFO, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  // @see Logger#vlog_at() --- LOG_LEVEL_WARNING
  public static function warn($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_WARNING, $msg, $fmt);
  }
  
  // @see Logger#vlog_at() --- LOG_LEVEL_WARNING
  public static function warn_at(Location $loc = null, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_WARNING, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  // @see Logger#vlog_at() --- LOG_LEVEL_ERROR
  public static function error($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_ERROR, $msg, $fmt);
  }
  
  // @see Logger#vlog_at() --- LOG_LEVEL_ERROR
  public static function error_at(Location $loc = null, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_ERROR, $msg, $fmt);
  }
}
