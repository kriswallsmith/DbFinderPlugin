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
   if (!isset($options['model']))
   {
     throw new InvalidArgumentException(sprintf('You must pass a "model" option for a %s object (%s).', get_class($this), $pattern));
   }

   if (!isset($options['type']))
   {
     throw new InvalidArgumentException(sprintf('You must pass a "type" option for a %s object (%s).', get_class($this), $pattern));
   }

   if (!in_array($options['type'], array('object', 'list', 'pager')))
   {
     throw new InvalidArgumentException(sprintf('The "type" option can only be "object", "list", or "pager", "%s" given (%s).', $options['type'], $pattern));
   }

   $this->pattern      = trim($pattern);
   $this->defaults     = $defaults;
   $this->requirements = $requirements;
   $this->options      = $options;
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
    return $this->getFinder($parameters)->findOne();
  }
  
  /**
   * Gets the list of objects related to the current route and parameters.
   *
   * This method is only accessible if the route is bound and of type "list".
   *
   * @param integer $limit The number of results to return (defaults to no limit)
   * 
   * @return array And array of related objects
   */
  public function getObjects($limit = null)
  {
    if (!$this->isBound())
    {
      throw new LogicException('The route is not bound.');
    }

    if ('list' != $this->options['type'])
    {
      throw new LogicException(sprintf('The route "%s" is not of type "list".', $this->pattern));
    }

    if (false !== $this->objects)
    {
      return $this->objects;
    }

    $this->objects = $this->getFinder($this->parameters)->find($limit);

    if (!count($this->objects) && isset($this->options['allow_empty']) && !$this->options['allow_empty'])
    {
      throw new sfError404Exception(sprintf('No %s object found for the following parameters "%s").', $this->options['model'], str_replace("\n", '', var_export($this->filterParameters($this->parameters), true))));
    }

    return $this->objects;
  }
  
  /**
   * Gets a pager of objects related to the current route and parameters.
   *
   * This method is only accessible if the route is bound and of type "pager".
   *
   * @param integer $page The current page (1 by default)
   * @param integer $maxPerPage The maximum number of results per page (10 by default)
   *
   * @return array And array of related objects
   */
  public function getObjectPager($page = 1, $maxPerPage = 10)
  {
    if (!$this->isBound())
    {
      throw new LogicException('The route is not bound.');
    }

    if ('pager' != $this->options['type'])
    {
      throw new LogicException(sprintf('The route "%s" is not of type "pager".', $this->pattern));
    }

    if (false !== $this->pager)
    {
      return $this->pager;
    }

    $this->pager = $this->getFinder($this->parameters)->paginate($page, $maxPerPage);

    if (!$this->pager->getNbResults() && isset($this->options['allow_empty']) && !$this->options['allow_empty'])
    {
      throw new sfError404Exception(sprintf('No %s object found for the following parameters "%s").', $this->options['model'], str_replace("\n", '', var_export($this->filterParameters($this->parameters), true))));
    }

    return $this->pager;
  }
  
  protected function getFinder($parameters)
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

    return $finder;
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