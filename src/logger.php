<?php

namespace phs;

require_once 'config.php';

use phs\front\Location;

const
  LOG_LEVEL_ALL     = 0,
  LOG_LEVEL_DEBUG   = 1, // debug messages
  LOG_LEVEL_VERBOSE = 2, // verbose
  LOG_LEVEL_INFO    = 3, // informations
  LOG_LEVEL_WARNING = 4, // warnings
  LOG_LEVEL_ERROR   = 5  // errors
;

class Logger
{
  // @var Session
  private static $sess;
  
  // @var int
  private static $level = LOG_LEVEL_ALL;
  
  // @var string|stream
  private static $dest = null; // -> stderr
  
  // @var bool  continue-flag
  private static $cont = false;
  
  // log-level hooks
  private static $hooks = [
    LOG_LEVEL_DEBUG => [],
    LOG_LEVEL_VERBOSE => [],
    LOG_LEVEL_INFO => [],
    LOG_LEVEL_WARNING => [],
    LOG_LEVEL_ERROR => []
  ];
  
  /* ------------------------------------ */
  
  public static function init(Session $sess)
  {
    self::$sess = $sess;
    
    // fetch config
    $conf = self::$sess->conf;
    
    if ($conf->has('log_dest'))
      self::set_dest($conf->get('log_dest'));
    
    if ($conf->has('log_level'))
      self::set_level((int)$conf->get('log_level'));
  }
  
  /* ------------------------------------ */
  
  public static function hook($lvl, callable $hook)
  {
    if ($lvl < 0 || $lvl > LOG_LEVEL_ERROR)
      $lvl = LOG_LEVEL_ERROR;
    
    array_push(self::$hooks[$lvl], $hook);
  }
  
  /* ------------------------------------ */
  
  public static function set_dest($dest)
  {
    if (!is_resource($dest)) {
      $dest = fopen($dest, 'w+');
      if (!$dest) exit('unable to set log-destination');
    }
    
    self::$dest = $dest;
  }
  
  public static function get_dest()
  {
    return self::$dest;
  }
  
  /* ------------------------------------ */
  
  public static function set_level($lvl)
  {
    if ($lvl < 0 || $lvl > LOG_LEVEL_ERROR)
      $lvl = LOG_LEVEL_DEBUG;
    
    self::$level = $lvl;
  }
  
  public static function get_level()
  {
    return self::$level;
  }
  
  /* ------------------------------------ */
  
  public static function log($lvl, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, $lvl, $msg, $fmt);
  }
  
  public static function log_at(Location $loc, $lvl, $msg)
  {
    $fmt = [];
    for ($i = 3, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, $lvl, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
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
    
    if ($lvl >= LOG_LEVEL_ERROR)
      self::$sess->abort = true;
    
    $out = '';
    
    if (!self::$cont) {
      switch ($lvl) {
        case LOG_LEVEL_DEBUG:
          $out .= '[dbg]   ';
          break;
        case LOG_LEVEL_INFO:
          $out .= '[info]  ';
          break;
        case LOG_LEVEL_WARNING:
          $out .= '[warn]  ';
          break;
        case LOG_LEVEL_ERROR:
          $out .= '[error] ';
          break;
      }
      
      if ($loc !== null)
        $out .= "{$loc->file}:{$loc->pos->line}:{$loc->pos->coln}: ";
    }
    
    self::$cont = $cont;
    
    $text = wordwrap($text, 80);
    $loop = false;
    $wrap = explode("\n", $text);
    $last = array_pop($wrap);
    $dest = self::$dest ?: STDERR;
    
    foreach ($wrap as $chnk) {
      if ($loop) $chnk .= "... $chnk";
      $loop = true;      
      fwrite($dest, "$out$chnk ...\n");
    }
    
    if ($wrap) $last = "... $last";
    fwrite($dest, "$out$last");
    if (!$cont) fwrite($dest, "\n");
  }
  
  /* ------------------------------------ */
  
  public static function debug($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_DEBUG, $msg, $fmt);
  }
  
  public static function debug_at(Location $loc, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_DEBUG, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  public static function verbose($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_VERBOSE, $msg, $fmt);
  }
  
  public static function verbose_at(Location $loc, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_VERBOSE, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  public static function info($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_INFO, $msg, $fmt);
  }
  
  public static function info_at(Location $loc, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_INFO, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  public static function warn($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_WARNING, $msg, $fmt);
  }
  
  public static function warn_at(Location $loc, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_WARNING, $msg, $fmt);
  }
  
  /* ------------------------------------ */
  
  public static function error($msg)
  {
    $fmt = [];
    for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at(null, LOG_LEVEL_ERROR, $msg, $fmt);
  }
  
  public static function error_at(Location $loc, $msg)
  {
    $fmt = [];
    for ($i = 2, $l = func_num_args(); $i < $l; ++$i)
      array_push($fmt, func_get_arg($i));
    
    self::vlog_at($loc, LOG_LEVEL_ERROR, $msg, $fmt);
  }
}
