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
  
  public static function getType($column, $orm)
  {
    $columnFinder = DbFinderAdapterUtils::getColumn($orm);
    return call_user_func(array($columnFinder, 'getColumnType'), $column);
  }
}