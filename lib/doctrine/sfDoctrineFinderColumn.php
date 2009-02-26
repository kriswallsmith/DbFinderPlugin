<?php

/*
 * This file is part of the sfPropelFinder package.
 * 
 * (c) 2008 François Zaninotto <francois.zaninotto@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfDoctrineFinderColumn maps column types from Doctrine to an internal column type rule
 * Currently supported ORMs are Doctrine 1.0
 */
class sfDoctrineFinderColumn extends DbFinderColumn
{
  private static $doctrineToDbFinderMap = array(
    'enum'      => self::INTEGER,
    'text'      => self::STRING,
    'object'    => self::STRING,
    'array'     => self::STRING,
    'string'    => self::STRING,
    'char'      => self::STRING,
    'gzip'      => self::STRING,
    'varchar'   => self::STRING,
    'clob'      => self::STRING,
    'blob'      => self::BLOB,
    'integer'   => self::INTEGER,
    'boolean'   => self::BOOLEAN,
    'int'       => self::INTEGER,
    'date'      => self::DATE,
    'time'      => self::TIME,
    'timestamp' => self::TIMESTAMP,
    'float'     => self::FLOAT,
    'double'    => self::DOUBLE,
    'decimal'   => self::DECIMAL,
  );
  
  public static function getColumnType($column)
  {
    // Doctrine
    if($type = $column->getType())
    {
      $type = array_key_exists($type, self::$doctrineToDbFinderMap) ? self::$doctrineToDbFinderMap[$type] : $type;
    }
    
    return $type;
  }
}