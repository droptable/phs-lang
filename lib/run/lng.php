<?php
/*!
 * This file is part of the PHS Runtime Library
 * Copyright (c) 2014 The PHS Team 
 * 
 * All rights reserved.
 * 
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published 
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * enables the "in" operator for objects
 * 
 * obj = new SomethingInable;
 * res = val in obj; // -> res = obj.contains(val)
 */
interface Inable {
  /**
   * should check if a value exists inside this object
   *
   * @param  mixed val
   * @return boolean
   */
  public function contains($val);
}

/**
 * predefined exceptions 
 */
class Error extends Exception
{
  public function __construct($msg, ...$args)
  {
    if (!empty ($args))
      $msg = sprintf($msg, $args);
    
    parent::__construct($msg, 9001);
  }
  
  // object-like api
  
  public function hash()
  {
    return spl_object_hash($this);
  }
  
  /**
   * default to-string
   *
   * @return string
   */
  public function __tostring()
  {
    return self::jtrace($this);
  }
  
  /**
   * jTraceEx variant
   * 
   * @link   http://php.net/manual/de/exception.gettraceasstring.php#114980
   * @author ernest@vogelsinger.at 
   *
   * @param  Error $e
   * @param  array $seen
   * @return string
   */
  protected static function jtrace($e, array $seen = null)
  {
    $starter = $seen ? 'Caused by: ' : '';
    $result = array();
    
    if (!$seen) $seen = array();
    
    $trace  = $e->getTrace();
    $prev   = $e->getPrevious();
    $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
    $file = $e->getFile();
    $line = $e->getLine();
    
    while (true) {
      $current = "$file:$line";
      
      if (is_array($seen) && in_array($current, $seen)) {
        $result[] = sprintf(' ... %d more', count($trace)+1);
        break;
      }
      
      $result[] = sprintf(' at %s%s%s(%s%s%s)',
        count($trace) && array_key_exists('class', $trace[0]) ? 
          str_replace('\\', '::', $trace[0]['class']) : '',
        count($trace) && array_key_exists('class', $trace[0]) && 
        array_key_exists('function', $trace[0]) ? 
          '.' : '',
        count($trace) && array_key_exists('function', $trace[0]) ? 
          str_replace('\\', '::', $trace[0]['function']) : '(main)',
        $line === null ? $file : basename($file),
        $line === null ? '' : ':',
        $line === null ? '' : $line
      );
        
      if (is_array($seen))
        $seen[] = "$file:$line";
      
      if (!count($trace))
        break;
      
      $file = array_key_exists('file', $trace[0]) ? 
        $trace[0]['file'] : 'Unknown Source';
        
      $line = array_key_exists('file', $trace[0]) && 
              array_key_exists('line', $trace[0]) && $trace[0]['line'] ? 
                $trace[0]['line'] : null;
                
      array_shift($trace);
    }
    
    $result = join("\n", $result);
    
    if ($prev)
      $result  .= "\n" . self::jtrace($prev, $seen);

    return $result;
  }
}

class ArgError extends Error {}
class TypeError extends Error {}
