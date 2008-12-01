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
 * DbFinderColumn maps column types from various ORMs to an internal column type rule
 * Currently supported ORMs are Propel 1.2, Propel 1.3, and Doctrine 1.0
 */
class DbFinderColumn
{
  const STRING = "STRING";
  const NUMERIC = "NUMERIC";
  const DECIMAL = "DECIMAL";
  const TINYINT = "TINYINT";
  const SMALLINT = "SMALLINT";
  const INTEGER = "INTEGER";
  const BIGINT = "BIGINT";
  const REAL = "REAL";
  const FLOAT = "FLOAT";
  const DOUBLE = "DOUBLE";
  const BINARY = "BINARY";
  const VARBINARY = "VARBINARY";
  const LONGVARBINARY = "LONGVARBINARY";
  const BLOB = "BLOB";
  const DATE = "DATE";
  const TIME = "TIME";
  const TIMESTAMP = "TIMESTAMP";
  const BU_DATE = "BU_DATE";
  const BU_TIMESTAMP = "BU_TIMESTAMP";
  const BOOLEAN = "BOOLEAN";
  
  private static $propel12ToDbFinderMap = array(
    1 => self::BOOLEAN,
    2 => self::BIGINT,
    3 => self::SMALLINT,
    4 => self::TINYINT,
    5 => self::INTEGER,
    6 => self::STRING,
    7 => self::STRING,
    8 => self::FLOAT,
    9 => self::DOUBLE,
    10 => self::DATE,
    11 => self::TIME,
    12 => self::TIMESTAMP,
    13 => self::VARBINARY,
    14 => self::NUMERIC,
    15 => self::BLOB,
    16 => self::STRING,
    17 => self::STRING,
    18 => self::DECIMAL,
    19 => self::REAL,
    20 => self::BINARY,
    21 => self::LONGVARBINARY,
    22 => self::INTEGER,
  );
  
  private static $propel13ToDbFinderMap = array(
    'CHAR'          => self::STRING,
    'VARCHAR'       => self::STRING,
    'LONGVARCHAR'   => self::STRING,
    'CLOB'          => self::STRING,
    'NUMERIC'       => self::NUMERIC,
    'DECIMAL'       => self::DECIMAL,
    'TINYINT'       => self::TINYINT,
    'SMALLINT'      => self::SMALLINT,
    'INTEGER'       => self::INTEGER,
    'BIGINT'        => self::BIGINT,
    'REAL'          => self::REAL,
    'FLOAT'         => self::FLOAT,
    'DOUBLE'        => self::DOUBLE,
    'BINARY'        => self::BINARY,
    'VARBINARY'     => self::VARBINARY,
    'LONGVARBINARY' => self::LONGVARBINARY,
    'BLOB'          => self::BLOB,
    'DATE'          => self::DATE,
    'TIME'          => self::TIME,
    'TIMESTAMP'     => self::TIMESTAMP,
    'BU_DATE'       => self::BU_DATE,
    'BU_TIMESTAMP'  => self::BU_TIMESTAMP,
    'BOOLEAN'       => self::BOOLEAN,
  );
  
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
  
  private static $version = null;
  
  public static function getType($column, $orm)
  {
    if($orm == DbFinderAdminGenerator::PROPEL)
    {
      if(is_null(self::$version))
      {
        self::$version = method_exists('ColumnMap', 'getCreoleType') ? 12 : 13;
      }
      if(self::$version == 12)
      {
        // Propel 1.2
        if($type = $column->getCreoleType())
        {
          $type = array_key_exists($type, self::$propel12ToDbFinderMap) ? self::$propel12ToDbFinderMap[$type] : $type;
        }
      }
      else
      {
        // Propel 1.3
        if($type = $column->getType())
        {
          $type = array_key_exists($type, self::$propel13ToDbFinderMap) ? self::$propel13ToDbFinderMap[$type] : $type;
        }
      }
    }
    else
    {
      // Doctrine
      if($type = $column->getType())
      {
        $type = array_key_exists($type, self::$doctrineToDbFinderMap) ? self::$doctrineToDbFinderMap[$type] : $type;
      }
    }
    
    return $type;
  }
}