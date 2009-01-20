<?php

/*
 * This file is part of the DbFinder package.
 * 
 * (c) 2009 François Zaninotto <francois.zaninotto@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Fakes a sfModelFinder instance by doing nothing until _endif() is called
 */
class sfModelFinderFake
{
  protected $finder;
  
  public function __construct($finder)
  {
    $this->finder = $finder;
  }
  
  public function _endif()
  {
    return $this->finder;
  }
  
  public function __call($name, $arguments)
  {
    return $this;
  }
}