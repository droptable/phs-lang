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
 * the root-object class.
 * every class has `Obj` as a superclass.
 */
class Obj 
{
  // PHP reserved words
  // see http://php.net/manual/en/reserved.keywords.php
  private static $rids = [
    '__halt_compiler', 'abstract', 'and', 'array', 'as',
    'break', 'callable', 'case', 'catch', 'class',
    'clone', 'const', 'continue', 'declare', 'default',
    'die', 'do', 'echo', 'else', 'elseif', 'empty',
    'enddeclare', 'endfor', 'endforeach', 'endif',
    'endswitch', 'endwhile', 'eval', 'exit', 'extends',
    'final', 'finally', 'for', 'foreach', 'function',
    'global', 'goto', 'if', 'implements', 'include',
    'include_once', 'instanceof', 'insteadof', 'interface',
    'isset', 'list', 'namespace', 'new', 'or', 'print',
    'private', 'protected', 'public', 'require', 'require_once',
    'return', 'static', 'switch', 'throw', 'trait', 'try',
    'unset', 'use', 'var', 'while', 'xor', 'yield',      
    '__class__', '__dir__', '__file__', '__function__',
    '__line__', '__method__', '__namespace__', '__trait__'   
  ];
  
  /**
   * returns the object-hash
   * 
   * @return string
   */
  public function hash()
  {
    return spl_object_hash($this);
  }
  
  /**
   * magic get
   *
   * @param  mixed  $key
   * @return mixed
   */
  public function __get($key)
  {
    // check mangled name
    if (strpos($key, -1, 1) === '_') {
      $rid = substr($key, 0, -1); 
      if (isset ($this->{$rid}) && in_array(strtolower($rid), self::$rids)) {
        // this property was mangled
        // define an alias
        $this->{$key} = &$this->{$rid};
        return $this->{$key}; 
      }
    }
    
    // not yet defined
    return null;
  }
  
  /**
   * gets called if a method was not found.
   * this implementation solves a fundamental problem in php:
   * 
   *     $foo = new stdClass; 
   *     $foo->bar = function() {};
   *     $foo->bar(); // <- does not work
   *     
   *     $bar = $foo->bar;
   *     $bar(); // <- works
   *     
   * the downside of this "workaround" is that closures can 
   * not retrieve variable-references anymore.
   *
   * @param  string $name
   * @param  array $args
   * @return mixed
   */
  public function __call($name, $args)
  {
    $prop = $this->$name;
    return $prop(...$args);
  }
  
  /**
   * default to-string method
   *
   * @return string
   */
  public function __tostring()
  {
    // returns something like:
    // <object Obj @ 000000003cc56d770000000007fa48c5>
    return '<object ' . get_class($this) 
      . ' @ ' . $this->hash() . '>';
  }
}
