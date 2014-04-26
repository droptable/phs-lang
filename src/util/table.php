<?php

namespace phs\util;

use \Countable;
use \ArrayIterator;
use \IteratorAggregate;

// a table works like a map, but can be namespaced (using rows)

/** cell */
interface Cell
{
  /**
   * should return a key that can be used in a table
   * 
   * @return string
   */
  public function key();
  
  /**
   * should return a row that can be used in a table
   *
   * @return int
   */
  public function row();
}

/** table */
abstract class Table implements IteratorAggregate, Countable
{
  // memory
  private $rows = [];
  
  /**
   * constructor
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * adds an cell
   * 
   * @param  Cell $itm
   * @return boolean
   */
  public function add(Cell $itm)
  {
    $key = $itm->key();
    $row = $itm->row();
    
    if (!isset ($this->rows[$row]))
      $this->rows[$row] = [];
    
    if (isset ($this->rows[$row][$key]))
      return false;
    
    $this->rows[$row][$key] = $itm;
    return true;
  }
  
  /**
   * returns a complete row
   * 
   * @param  int $row
   * @return array
   */
  public function get($row)
  {
    if (isset ($this->rows[$row]))
      return $this->rows[$row];
    
    return null;
  }
  
  /**
   * checks if a cell exists
   * 
   * @param  Cell    $itm
   * @return boolean
   */
  public function has(Cell $itm)
  {
    $key = $itm->key();
    $row = $itm->row();
    
    return isset ($this->rows[$row]) &&  
           isset ($this->rows[$row][$key]);
  }
  
  /**
   * removes a cell
   * 
   * @param  Cell   $itm
   * @return boolean  true if the cell was assigned, false otherwise
   */
  public function delete(Cell $itm)
  {
    $key = $itm->key();
    $row = $itm->row();
    
    if (isset ($this->rows[$row]) &&  
        isset ($this->rows[$row][$key])) {
      unset ($this->rows[$row][$key]);
      return true;
    }
    
    return false;
  }
  
  /* ------------------------------------ */
  /* IteratorAggregate */
  
  public function getIterator()
  {
    return new ArrayIterator($this->rows);
  }
  
  /* ------------------------------------ */
  /* Countable */
  
  public function count()
  {
    $count = 0;
    
    foreach ($this->rows as $row)
      foreach ($row as $itm)
        ++$count;
      
    return $count;
  }
}
