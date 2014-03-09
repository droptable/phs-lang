<?php

namespace phs;

interface Writer 
{
  public function write($d);
  public function seek($n);
}

/** memory writer */
class MemoryWriter implements Writer
{
  private $data;
  
  public function write($data)
  {
    $this->data .= $data;
  }
  
  public function seek($n)
  {
    $this->data = substr($this->data, $n);
  }
  
  public function get_data()
  {
    return $this->data;
  }
}

/** file buffer */
class FileWriter implements Writer
{
  private $fp;
  
  public function __construct($path)
  {
    $this->fp = fopen($path, 'w+');
  }
  
  public function write($data)
  {
    fwrite($this->fp, $data);
  }
  
  public function seek($n)
  {
    fseek($this->fp, $n);
  }
  
  public function __destruct()
  {
    fclose($this->fp);
  }
}
