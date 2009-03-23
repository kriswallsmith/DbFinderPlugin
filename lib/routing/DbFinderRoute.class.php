<?php

/*
 * This file is part of the DbFinder package.
 * (c) Francois Zaninotto <francois.zaninotto@symfony-project.com>
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * DbFinderRoute represents a route that is bound to a Model class.
 *
 * A DbFinderRoute route can represent a single Model object or a list of objects.
 *
 * @package    DbFinder
 * @author     Francois Zaninotto <francois.zaninotto@symfony-project.com>
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DbFinderRoute extends sfObjectRoute
{
  protected
    $finder = null;

  /**
   * Constructor.
   *
   * @param string $pattern       The pattern to match
   * @param array  $defaults      An array of default parameter values
   * @param array  $requirements  An array of requirements for parameters (regexes)
   * @param array  $options       An array of options
   *
   * @see sfObjectRoute
   */
  public function __construct($pattern, array $defaults = array(), array $requirements = array(), array $options = array())
  {
    parent::__construct($pattern, $defaults, $requirements, $options);

    $this->options['object_model'] = $this->options['model'];
  }
  
  public function setListFinder(sfModelFinder $finder)
  {
    if (!$this->isBound())
    {
      throw new LogicException('The route is not bound.');
    }

    $this->finder = $finder;
  }

  protected function getObjectForParameters($parameters)
  {
    return $this->getForParameters($parameters, 'findOne');
  }
  
  protected function getObjectsForParameters($parameters)
  {
    return $this->getForParameters($parameters, 'find');
  }
  
  protected function getForParameters($parameters, $method = 'find')
  {
    if (!isset($this->options['method']))
    {
      if (is_null($this->finder))
      {
        $finder = DbFinder::from($this->options['model']);
        foreach ($this->getRealVariables() as $variable)
        {
          $camlVariable = sfInflector::camelize($variable);
          $customWhere = 'where' . $camlVariable;
          if(method_exists($finder, $customWhere))
          {
            $finder->$customWhere($parameters[$variable]);
          }
          else
          {
            try
            {
              $finder->where(sfInflector::camelize($variable), $parameters[$variable]);
            }
            catch (Exception $e)
            {
              // don't add condition if the variable cannot be mapped to a column
            }
            
          }
        }
      }
      else
      {
        $finder = $this->finder;
      }
      if (isset($this->options['finder_methods']))
      {
        foreach ($this->options['finder_methods'] as $finder_methods)
        {
          $finder->$finder_methods();
        }
      }

      $results = $finder->$method();
    }
    else
    {
      $method = $this->options['method'];
      $results = DbFinder::from($this->options['model'])->$method($this->filterParameters($parameters));
    }

    return $results;
  }

  protected function doConvertObjectToArray($object)
  {
    if (isset($this->options['convert']) || method_exists($object, 'toParams'))
    {
      return parent::doConvertObjectToArray($object);
    }

    $parameters = array();
    foreach ($this->getRealVariables() as $variable)
    {
      try
      {
        $method = 'get'.sfInflector::camelize($variable);
        $parameters[$variable] = $object->$method;
      }
      catch (Exception $e)
      {
        // don't add value if the variable cannot be mapped to a column
      }
    }

    return $parameters;
  }
  
  protected function getRealVariables()
  {
    return isset($this->options['object_variables']) ? $this->options['object_variables'] : parent::getRealVariables();
  }
}