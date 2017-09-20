<?php

/**
 * Model base class. Your model objects should extend
 * this class. A minimal subclass would look like:
 *
 * class Widget extends Model {
 * }
 *
 */
class Model
{
    // Default ID column for all models. Can be overridden by adding
    // a public static _id_column property to your model classes.
    const ORM_PRIMARY_KEY = 'id';
    const ORM_FOREIGN_KEY = '_id';      // Default foreign key used by relationship methods

    /**
     * The ORM instance used by this model
     * instance to communicate with the database.
     */
    public $orm;

    /**
     * Retrieve the value of a static property on a class. If the
     * class or the property does not exist, returns the default
     * value supplied as the third argument (which defaults to null).
     */
    protected static function _get_static_property($classname, $property, $default=null)
    {
        if (!class_exists($classname) || !property_exists($classname, $property)) {
            return $default;
        }
        $properties = get_class_vars($classname);
        return $properties[$property];
    }

    /**
     * Static method to get a table name given a class name.
     * If the supplied class has a public static property
     * named $_table, the value of this property will be
     * returned. If not, the class name will be converted using
     * the _classname_to_table method method.
     */
    protected static function _getTable($classname)
    {
        $specified_table = self::_get_static_property($classname, '_table');
        if (is_null($specified_table)) {
            return self::_classname_to_table($classname);
        }
        return $specified_table;
    }

    /**
     * Convert a namespace to the standard PEAR underscore format.
     *
     * Then convert a class name in CapWords to a table name in
     * lowercase_with_underscores.
     *
     * Finally strip doubled up underscores
     *
     * For example, CarTyre would be converted to car_tyre. And
     * Project\Models\CarTyre would be project_models_car_tyre.
     */
    protected static function _classname_to_table($classname)
    {
        return strtolower(preg_replace(
            array('/\\\\/', '/(?<=[a-z])([A-Z])/', '/__/'),
            array('_', '_$1', '_'),
            $classname
        ));
    }

    /**
     * Return the ID column name to use for this class. If it is
     * not set on the class, returns null.
     */
    protected static function _get_id_column_name($classname)
    {
        return self::_get_static_property($classname, '_id_column', self::ORM_PRIMARY_KEY);
    }

    /**
     * Build a foreign key based on a table name. If the first argument
     * (the specified foreign key column name) is null, returns the second
     * argument (the name of the table) with the default foreign key column
     * suffix appended.
     */
    protected static function _buildForeignKey($specified_foreign_key_name, $table)
    {
        if (!is_null($specified_foreign_key_name)) {
            return $specified_foreign_key_name;
        }
        return $table . self::ORM_FOREIGN_KEY;
    }

    /**
     * Factory method used to acquire instances of the given class.
     * The class name should be supplied as a string, and the class
     * should already have been loaded by PHP (or a suitable autoloader
     * should exist). This method actually returns a wrapped ORM object
     * which allows a database query to be built. The wrapped ORM object is
     * responsible for returning instances of the correct class when
     * its findOne or findMany methods are called.
     */
    public static function factory($classname)
    {
        $table = self::_getTable($classname);
        $wrapper = ORMWrapper::table($table);
        $wrapper->setClassname($classname);
        $wrapper->use_id_column(self::_get_id_column_name($classname));
        return $wrapper;
    }

    /**
     * Internal method to construct the queries for both the has_one and
     * has_many methods. These two types of association are identical; the
     * only difference is whether findOne or findMany is used to complete
     * the method chain.
     */
    protected function _has_one_or_many($associated_classname, $foreign_key_name=null)
    {
        $base_table = self::_getTable(get_class($this));
        $foreign_key_name = self::_buildForeignKey($foreign_key_name, $base_table);
        return self::factory($associated_classname)->where($foreign_key_name, $this->id());
    }

    /**
     * Helper method to manage one-to-one relations where the foreign
     * key is on the associated table.
     */
    protected function hasOne($associated_classname, $foreign_key_name=null)
    {
        return $this->_has_one_or_many($associated_classname, $foreign_key_name);
    }

    /**
     * Helper method to manage one-to-many relations where the foreign
     * key is on the associated table.
     */
    protected function hasMany($associated_classname, $foreign_key_name=null)
    {
        return $this->_has_one_or_many($associated_classname, $foreign_key_name);
    }

    /**
     * Helper method to manage one-to-one and one-to-many relations where
     * the foreign key is on the base table.
     */
    protected function belongsTo($associated_classname, $foreign_key_name=null)
    {
        $associated_table = self::_getTable($associated_classname);
        $foreign_key_name = self::_buildForeignKey($foreign_key_name, $associated_table);
        $associated_object_id = $this->$foreign_key_name;
        return self::factory($associated_classname)->whereIdIs($associated_object_id);
    }

    /**
     * Helper method to manage many-to-many relationships via an intermediate model. See
     * README for a full explanation of the parameters.
     */
    protected function hasManyThrough($associated_classname, $join_classname=null, $key_to_base_table=null, $key_to_associated_table=null)
    {
        $base_classname = get_class($this);

        // The class name of the join model, if not supplied, is
        // formed by concatenating the names of the base class
        // and the associated class, in alphabetical order.
        if (is_null($join_classname)) {
            $classnames = array($base_classname, $associated_classname);
            sort($classnames, SORT_STRING);
            $join_classname = join("", $classnames);
        }

        // Get table names for each class
        $base_table = self::_getTable($base_classname);
        $associated_table = self::_getTable($associated_classname);
        $join_table = self::_getTable($join_classname);

        // Get ID column names
        $base_table_id_column = self::_get_id_column_name($base_classname);
        $associated_table_id_column = self::_get_id_column_name($associated_classname);

        // Get the column names for each side of the join table
        $key_to_base_table = self::_buildForeignKey($key_to_base_table, $base_table);
        $key_to_associated_table = self::_buildForeignKey($key_to_associated_table, $associated_table);

        return self::factory($associated_classname)
        ->select("{$associated_table}.*")
        ->join($join_table, array("{$associated_table}.{$associated_table_id_column}", '=', "{$join_table}.{$key_to_associated_table}"))
        ->where("{$join_table}.{$key_to_base_table}", $this->id());
    }

    /**
     * Set the wrapped ORM instance associated with this Model instance.
     */
    public function _setORM($orm)
    {
        $this->orm = $orm;
    }

    /**
     * Magic getter method, allows $model->property access to data.
     */
    public function __get($property)
    {
        return $this->orm->get($property);
    }

    /**
     * Magic setter method, allows $model->property = 'value' access to data.
     */
    public function __set($property, $value)
    {
        $this->orm->set($property, $value);
    }

    /**
     * Magic isset method, allows isset($model->property) to work correctly.
     */
    public function __isset($property)
    {
        return $this->orm->__isset($property);
    }

    /**
     * Getter method, allows $model->get('property') access to data
     */
    public function get($property)
    {
        return $this->orm->get($property);
    }

    /**
     * Setter method, allows $model->set('property', 'value') access to data.
     */
    public function set($property, $value = null)
    {
        $this->orm->set($property, $value);
    }

    /**
     * Check whether the given field has changed since the object was created or saved
     */
    public function hasChanged($property)
    {
        return $this->orm->hasChanged($property);
    }

    /**
     * Wrapper for ORM's toArray method.
     */
    public function toArray()
    {
        $args = func_get_args();
        return call_user_func_array(array($this->orm, 'toArray'), $args);
    }

    /**
     * Hydrate this model instance with an associative array of data.
     * WARNING: The keys in the array MUST match with columns in the
     * corresponding database table. If any keys are supplied which
     * do not match up with columns, the database will throw an error.
     */
    public function hydrate($data)
    {
        $this->orm->hydrate($data)->force_all_dirty();
    }

    /**
     * Get the database ID of this model instance.
     */
    public function id()
    {
        return $this->orm->id();
    }

    /**
     * Save the data associated with this model instance to the database.
     */
    public function save()
    {
        return $this->orm->save();
    }

    /**
     * Delete the database row associated with this model instance.
     */
    public function delete()
    {
        return $this->orm->delete();
    }
}
