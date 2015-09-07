<?php

namespace Waldo\DatatableBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Waldo\DatatableBundle\Util\Factory\Query\QueryInterface;
use Waldo\DatatableBundle\Util\Factory\Query\DoctrineBuilder;
use Waldo\DatatableBundle\Util\Formatter\Renderer;
use Waldo\DatatableBundle\Util\Factory\Prototype\PrototypeBuilder;

class Datatable
{

    /** @var array */
    protected $_fixed_data = NULL;

    /** @var array */
    protected $_config;

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface */
    protected $_container;

    /** @var \Doctrine\ORM\EntityManager */
    protected $_em;

    /** @var boolean */
    protected $_has_action;

    /** @var boolean */
    protected $_has_renderer_action = false;

    /** @var array */
    protected $_multiple;

    /** @var \Waldo\DatatableBundle\Util\Factory\Query\QueryInterface */
    protected $_queryBuilder;

    /** @var \Symfony\Component\HttpFoundation\Request */
    protected $_request;

    /** @var closure */
    protected $_renderer = NULL;

    /** @var array */
    protected $_renderers = NULL;

    /** @var Renderer */
    protected $_renderer_obj = null;

    /** @var boolean */
    protected $_search;

    /** @var boolean */
    protected $_global_search = true;

    /** @var array */
    protected $_search_fields = array();

    /** @var array */
    protected $_not_filterable_fields = array();

    /** @var array */
    protected $_not_sortable_fields = array();

    /** @var array */
    protected $_hidden_fields = array();

    /** @var array */
    protected static $_instances = array();

    /** @var Datatable */
    protected static $_current_instance = NULL;

    /**
     * class constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->_container    = $container;
        $this->_config       = $this->_container->getParameter('datatable');
        $this->_em           = $this->_container->get('doctrine.orm.entity_manager');
        $this->_request      = $this->_container->get('request');
        $this->_queryBuilder = new DoctrineBuilder($container);
        self::$_current_instance = $this;
        $this->applyDefaults();
    }

    /**
     * apply default value from datatable config
     *
     * @return void
     */
    protected function applyDefaults()
    {
        if (isset($this->_config['all']))
        {
            $this->_has_action = $this->_config['all']['action'];
            $this->_search     = $this->_config['all']['search'];
        }
    }

    /**
     * add join
     *
     * @example:
     *      ->setJoin(
     *              'r.event',
     *              'e',
     *              \Doctrine\ORM\Query\Expr\Join::INNER_JOIN,
     *              'e.name like %test%')
     *
     * @param string $join_field
     * @param string $alias
     * @param string $type
     * @param string $cond
     *
     * @return Datatable
     */
    public function addJoin($join_field, $alias, $type = Join::INNER_JOIN, $cond = '')
    {
        $this->_queryBuilder->addJoin($join_field, $alias, $type, $cond);
        return $this;
    }

    /**
     * execute
     *
     * @param int $hydration_mode
     *
     * @return JsonResponse
     */
    public function execute($hydration_mode = Query::HYDRATE_ARRAY)
    {
        $request       = $this->_request;
        $iTotalRecords = $this->_queryBuilder->getTotalRecords();
        $iTotalDisplayRecords = $this->_queryBuilder->getTotalDisplayRecords();
        list($data, $objects) = $this->_queryBuilder->getData($hydration_mode);

        $id_index      = array_search('_identifier_', array_keys($this->getFields()));
        $ids           = array();

        array_walk($data, function($val, $key) use ($id_index, &$ids) {
            $ids[$key] = $val[$id_index];
        });

        if (!is_null($this->_fixed_data))
        {
            $this->_fixed_data = array_reverse($this->_fixed_data);
            foreach ($this->_fixed_data as $item)
            {
                array_unshift($data, $item);
            }
        }
        if (!is_null($this->_renderer))
        {
            array_walk($data, $this->_renderer);
        }
        if (!is_null($this->_renderer_obj))
        {
            $this->_renderer_obj->applyTo($data,$objects);
        }
        if (!empty($this->_multiple))
        {
            array_walk($data, function($val, $key) use(&$data, $ids) {
                array_unshift($val, "<input type='checkbox' name='dataTables[actions][]' value='{$ids[$key]}' />");
                $data[$key] = $val;
            });
        }
        $output = array(
            "sEcho"                => intval($request->get('sEcho')),
            "iTotalRecords"        => $iTotalRecords,
            "iTotalDisplayRecords" => $iTotalDisplayRecords,
            "aaData"               => $data
        );
        return new JsonResponse($output);
    }

    /**
     * get datatable instance by id
     *  return current instance if null
     *
     * @param string $id
     *
     * @return Datatable .
     */
    public static function getInstance($id)
    {
        $instance = NULL;
        if (array_key_exists($id, self::$_instances))
        {
            $instance = self::$_instances[$id];
        }
        else
        {
            $instance = self::$_current_instance;
        }

        if (is_null($instance))
        {
            throw new \Exception('No instance found for datatable, you should set a datatable id in your
            action with "setDatatableId" using the id from your view ');
        }

        return $instance;
    }

    public static function clearInstance()
    {
        self::$_instances = array();
    }


    /**
     * get entity name
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->_queryBuilder->getEntityName();
    }

    /**
     * get entity alias
     *
     * @return string
     */
    public function getEntityAlias()
    {
        return $this->_queryBuilder->getEntityAlias();
    }

    /**
     * get fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->_queryBuilder->getFields();
    }

    /**
     * get has_action
     *
     * @return boolean
     */
    public function getHasAction()
    {
        return $this->_has_action;
    }

    /**
     * retrun true if the actions column is overridden by twig renderer
     *
     * @return boolean
     */
    public function getHasRendererAction()
    {
        return $this->_has_renderer_action;
    }

    /**
     * get order field
     *
     * @return string
     */
    public function getOrderField()
    {
        return $this->_queryBuilder->getOrderField();
    }

    /**
     * get order type
     *
     * @return string
     */
    public function getOrderType()
    {
        return $this->_queryBuilder->getOrderType();
    }

    /**
     * create raw prototype
     *
     * @param string $type
     *
     * @return PrototypeBuilder
     */
    public function getPrototype($type)
    {
        return new PrototypeBuilder($this->_container, $type);
    }

    /**
     * get query builder
     *
     * @return QueryInterface
     */
    public function getQueryBuilder()
    {
        return $this->_queryBuilder;
    }

    /**
     * get search
     *
     * @return boolean
     */
    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * get global_search
     *
     * @return boolean
     */
    public function getGlobalSearch()
    {
        return $this->_global_search;
    }

    /**
     * set entity
     *
     * @param type $entity_name
     * @param type $entity_alias
     *
     * @return Datatable
     */
    public function setEntity($entity_name, $entity_alias)
    {
        $this->_queryBuilder->setEntity($entity_name, $entity_alias);
        return $this;
    }

    /**
     * set fields
     *
     * @param array $fields
     *
     * @return Datatable
     */
    public function setFields(array $fields)
    {
        $this->_queryBuilder->setFields($fields);
        return $this;
    }

    /**
     * set has action
     *
     * @param type $has_action
     *
     * @return Datatable
     */
    public function setHasAction($has_action)
    {
        $this->_has_action = $has_action;
        return $this;
    }

    /**
     * set order
     *
     * @param type $order_field
     * @param type $order_type
     *
     * @return Datatable
     */
    public function setOrder($order_field, $order_type)
    {
        $this->_queryBuilder->setOrder($order_field, $order_type);
        return $this;
    }

    /**
     * set fixed data
     *
     * @param type $data
     *
     * @return Datatable
     */
    public function setFixedData($data)
    {
        $this->_fixed_data = $data;
        return $this;
    }

    /**
     * set query builder
     *
     * @param QueryInterface $queryBuilder
     */
    public function setQueryBuilder(QueryInterface $queryBuilder)
    {
        $this->_queryBuilder = $queryBuilder;
    }

    /**
     * set a php closure as renderer
     *
     * @example:
     *
     *  $controller_instance = $this;
     *  $datatable = $this->get('datatable')
     *       ->setEntity("BaseBundle:Entity", "e")
     *       ->setFields($fields)
     *       ->setOrder("e.created", "desc")
     *       ->setRenderer(
     *               function(&$data) use ($controller_instance)
     *               {
     *                   foreach ($data as $key => $value)
     *                   {
     *                       if ($key == 1)
     *                       {
     *                           $data[$key] = $controller_instance
     *                               ->get('templating')
     *                               ->render('BaseBundle:Entity:_decorator.html.twig',
     *                                       array(
     *                                           'data' => $value
     *                                       )
     *                               );
     *                       }
     *                   }
     *               }
     *         )
     *       ->setHasAction(true);
     *
     * @param \Closure $renderer
     *
     * @return Datatable
     */
    public function setRenderer(\Closure $renderer)
    {
        $this->_renderer = $renderer;
        return $this;
    }

    /**
     * set renderers as twig views
     *
     * @example: To override the actions column
     *
     *      ->setFields(
     *          array(
     *             "field label 1" => 'x.field1',
     *             "field label 2" => 'x.field2',
     *             "_identifier_"  => 'x.id'
     *          )
     *      )
     *      ->setRenderers(
     *          array(
     *             2 => array(
     *               'view' => 'WaldoDatatableBundle:Renderers:_actions.html.twig',
     *               'params' => array(
     *                  'edit_route'    => 'matche_edit',
     *                  'delete_route'  => 'matche_delete',
     *                  'delete_form_prototype'   => $datatable->getPrototype('delete_form')
     *               ),
     *             ),
     *          )
     *       )
     *
     * @param array $renderers
     *
     * @return Datatable
     */
    public function setRenderers(array $renderers)
    {
        $this->_renderers = $renderers;
        if (!empty($this->_renderers))
        {
            $this->_renderer_obj = new Renderer($this->_container, $this->_renderers, $this->getFields());
        }
        $actions_index = array_search('_identifier_', array_keys($this->getFields()));
        if ($actions_index != FALSE && isset($renderers[$actions_index]))
        {
            $this->_has_renderer_action = true;
        }
        return $this;
    }

    /**
     * set query where
     *
     * @param string $where
     * @param array  $params
     *
     * @return Datatable
     */
    public function setWhere($where, array $params = array())
    {
        $this->_queryBuilder->setWhere($where, $params);
        return $this;
    }

    /**
     * set query group
     *
     * @param string $groupbywhere
     *
     * @return Datatable
     */
    public function setGroupBy($groupby)
    {
        $this->_queryBuilder->setGroupBy($groupby);
        return $this;
    }

    /**
     * set search
     *
     * @param bool $search
     *
     * @return Datatable
     */
    public function setSearch($search)
    {
        $this->_search = $search;
        $this->_queryBuilder->setSearch($search || $this->_global_search);
        return $this;
    }

    /**
     * set global search
     *
     * @param bool $global_search
     *
     * @return Datatable
     */
    public function setGlobalSearch($global_search)
    {
        $this->_global_search = $global_search;
        $this->_queryBuilder->setSearch($global_search || $this->_search);
        return $this;
    }


    /**
     * set datatable identifier
     *
     * @param string $id
     *
     * @return Datatable
     */
    public function setDatatableId($id)
    {
        if (!array_key_exists($id, self::$_instances))
        {
            self::$_instances[$id] = $this;
        }
        else
        {
            throw new \Exception('Identifer already exists');
        }
        return $this;
    }

    /**
     * get multiple
     *
     * @return array
     */
    public function getMultiple()
    {
        return $this->_multiple;
    }

    /**
     * set multiple
     *
     * @example
     *
     *  ->setMultiple('delete' => array ('title' => "Delete", 'route' => 'route_to_delete' ));
     *
     * @param array $multiple
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setMultiple(array $multiple)
    {
        $this->_multiple = $multiple;
        return $this;
    }

    /**
     * get global configuration ( read it from config.yml under datatable)
     *
     * @return array
     */
    public function getConfiguration()
    {
        return $this->_config;
    }

    /**
     * get search field
     *
     * @return array
     */
    public function getSearchFields()
    {
        return $this->_search_fields;
    }

    /**
     * set search fields
     *
     * @example
     *
     *      ->setSearchFields(array(0,2,5))
     *
     * @param array $search_fields
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setSearchFields(array $search_fields)
    {
        $this->_search_fields = $search_fields;
        return $this;
    }

    /**
     * set not filterable fields
     *
     * @example
     *
     *      ->setNotFilterableFields(array(0,2,5))
     *
     * @param array $not_filterable_fields
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setNotFilterableFields(array $not_filterable_fields)
    {
        $this->_not_filterable_fields = $not_filterable_fields;
        return $this;
    }

    /**
     * get not filterable field
     *
     * @return array
     */
    public function getNotFilterableFields()
    {
        return $this->_not_filterable_fields;
    }

    /**
     * set not sortable fields
     *
     * @example
     *
     *      ->setNotSortableFields(array(0,2,5))
     *
     * @param array $not_sortable_fields
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setNotSortableFields(array $not_sortable_fields)
    {
        $this->_not_sortable_fields = $not_sortable_fields;
        return $this;
    }

    /**
     * get not sortable field
     *
     * @return array
     */
    public function getNotSortableFields()
    {
        return $this->_not_sortable_fields;
    }

    /**
     * set hidden fields
     *
     * @example
     *
     *      ->setHiddenFields(array(0,2,5))
     *
     * @param array $hidden_fields
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setHiddenFields(array $hidden_fields)
    {
        $this->_hidden_fields = $hidden_fields;
        return $this;
    }

    /**
     * get hidden field
     *
     * @return array
     */
    public function getHiddenFields()
    {
        return $this->_hidden_fields;
    }

    /**
     * set filtering type
     * 's' strict
     * 'f' full => LIKE '%' . $value . '%'
     * 'b' begin => LIKE '%' . $value
     * 'e' end => LIKE $value . '%'
     *
     * @example
     *
     *      ->setFilteringType(array(0 => 's',2 => 'f',5 => 'b'))
     *
     * @param array $filtering_type
     *
     * @return \Waldo\DatatableBundle\Util\Datatable
     */
    public function setFilteringType(array $filtering_type)
    {
        $this->_queryBuilder->setFilteringType($filtering_type);
        return $this;
    }

}
