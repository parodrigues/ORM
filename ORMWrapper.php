<?php

/**
 *
 * A simple Active Record implementation built on top of ORM
 *
 * You should include ORM before you include this file:
 * require_once 'your/path/to/ORM.php';
 *
 * Subclass of ORM's ORM class that supports
 * returning instances of a specified class rather
 * than raw instances of the ORM class.
 *
 * You shouldn't need to interact with this class
 * directly. It is used internally by the Model base
 * class.
 */
class ORMWrapper extends ORM
{
    /**
     * The wrapped findOne and findMany classes will
     * return an instance or instances of this class.
     */
    protected $_classname;

    /**
     * Set the name of the class which the wrapped
     * methods should return instances of.
     */
    public function setClassname($classname)
    {
        $this->_classname = $classname;
    }

    /**
     * Method to create an instance of the model class
     * associated with this wrapper and populate
     * it with the supplied ORM instance.
     */
    protected function _create_model_instance($orm)
    {
        if ($orm === false) {
            return false;
        }
        $model = new $this->_classname();
        $model->_setORM($orm);
        return $model;
    }

    /**
     * Factory method, return an instance of this
     * class bound to the supplied table name.
     */
    public static function table($table)
    {
        self::_setup_db();
        return new self($table);
    }

    /**
     * Wrap ORM's create method to return an
     * empty instance of the class associated with
     * this wrapper instead of the raw ORM class.
     */
    public function create($data=null)
    {
        return $this->_create_model_instance(parent::create($data));
    }

    /**
     * Wrap ORM's findOne method to return
     * an instance of the class associated with
     * this wrapper instead of the raw ORM class.
     */
    public function findOne($id=null)
    {
        return $this->_create_model_instance(parent::findOne($id));
    }

    /**
     * Wrap ORM's findMany method to return
     * an array of instances of the class associated
     * with this wrapper instead of the raw ORM class.
     */
    public function findMany()
    {
        return array_map(array($this, '_create_model_instance'), parent::findMany());
    }

    /**
     * Add a custom filter to the method chain specified on the
     * model class. This allows custom queries to be added
     * to models. The filter should take an instance of the
     * ORM wrapper as its first argument and return an instance
     * of the ORM wrapper. Any arguments passed to this method
     * after the name of the filter will be passed to the called
     * filter function as arguments after the ORM class.
     */
    public function filter()
    {
        $args   = func_get_args();
        $filter = array_shift($args);
        array_unshift($args, $this);
        if (method_exists($this->_classname, $filter)) {
            return call_user_func_array(array($this->_classname, $filter), $args);
        }
    }
}

