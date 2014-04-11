<?php

namespace phs;

class Config
{
  private $data;
  
  public function set($k, $v)
  {
    $this->data[$k] = $v;
  }
  
  public function get($k)
  {
    return $this->data[$k];
  }
  
  public function has($k)
  {
    return isset ($this->data[$k]);
  }
  
  public function delete($k)
  {
    unset ($this->data[$k]);
  }
  
  /* ------------------------------------ */
  
  public function set_defaults()
  {
    $this->set('log_dest', null);
    $this->set('log_level', LOG_LEVEL_ALL);
  }
}
