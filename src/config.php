<?php

namespace phs;

class Config
{  
  public function set($k, $v)
  {
    $this->$k = $v;
  }
  
  public function get($k)
  {
    return $this->$k;
  }
  
  public function has($k)
  {
    return isset ($this->$k);
  }
  
  public function delete($k)
  {
    unset ($this->$k);
  }
  
  /* ------------------------------------ */
  
  public function set_defaults()
  {
    $this->set('log_dest', null);
    $this->set('log_level', LOG_LEVEL_ALL);
  }
}
