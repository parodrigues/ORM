<?php

/**
 *
 * ORM
 *
 * A single-class super-simple database abstraction layer for PHP.
 * Provides (nearly) zero-configuration object-relational mapping
 * and a fluent interface for building basic, commonly-used queries.
 *
 */

class ORM
{
    // Where condition array keys
    const WHERE_FRAGMENT = 0;
    const WHERE_VALUES = 1;

    // Class configuration
    protected static $_config = array(
        'orm.dsn'             => 'sqlite::memory:',
        'orm.username'        => null,
        'orm.password'        => null,
        'orm.driver_options'  => null,
        'orm.error_mode'      => PDO::ERRMODE_EXCEPTION,
        'orm.quote_character' => null,
        'orm.id_column'       => 'id',
        'orm.id_overrides'    => array(),
        'orm.logging'         => false,
        'orm.caching'         => false,
    );

    // Database connection, instance of the PDO class
    protected static $_db;

    // Last query run, only populated if logging is enabled
    protected static $_lastQuery;

    // Log of all queries run, only populated if logging is enabled
    protected static $_query_log = array();

    // Query cache, only used if query caching is enabled
    protected static $_query_cache = array();

    // The data for a hydrated instance of the class
    protected $_data = array();

    // The name of the table the current ORM instance is associated with
    protected $_table;

    // Alias for the table to be used in SELECT queries
    protected $_alias = null;

    // Columns to select in the result
    protected $_columns = array('*');

    // Are we using the default result column or have these been manually changed?
    protected $_using_default_columns = true;

    // Values to be bound to the query
    protected $_values = array();

    // Join sources
    protected $_joins = array();

    // Should the query include a DISTINCT keyword?
    protected $_distinct = false;

    // Is this a raw query?
    protected $_is_raw_query = false;

    // The raw query
    protected $_raw_query = '';

    // The raw query parameters
    protected $_raw_parameters = array();

    // Array of WHERE clauses
    protected $_where = array();

    // LIMIT
    protected $_limit = null;

    // OFFSET
    protected $_offset = null;

    // ORDER BY
    protected $_orderBy = array();

    // GROUP BY
    protected $_groupBy = array();

    // Fields that have been modified during the
    // lifetime of the object
    protected $_dirty_fields = array();

    // Fields that are to be inserted in the DB raw
    protected $_expr_fields = array();

    // Is this a new object (has create() been called)?
    protected $_is_new = false;

    // Name of the column to use as the primary key for
    // this instance only. Overrides the config settings.
    protected $_id_column = null;

    /**
     * Pass configuration settings to the class in the form of
     * key/value pairs. As a shortcut, if the second argument
     * is omitted and the key is a string, the setting is
     * assumed to be the DSN string used by PDO to connect
     * to the database (often, this will be the only configuration
     * required to use ORM). If you have more than one setting
     * you wish to configure, another shortcut is to pass an array
     * of settings (and omit the second argument).
     */
    public static function configure($key, $value=null)
    {
        if (is_array($key)) {
            // Shortcut: If only one array argument is passed,
            // assume it's an array of configuration settings
            foreach ($key as $conf_key => $conf_value) {
                self::configure($conf_key, $conf_value);
            }
        } else {
            if (is_null($value)) {
                // Shortcut: If only one string argument is passed,
                // assume it's a connection string
                $value = $key;
                $key = 'orm.dsn';
            }
            self::$_config[$key] = $value;
        }
    }

    /**
     * Despite its slightly odd name, this is actually the factory
     * method used to acquire instances of the class. It is named
     * this way for the sake of a readable interface, ie
     * ORM::table('table')->findOne()-> etc. As such,
     * this will normally be the first method called in a chain.
     */
    public static function table($table)
    {
        self::_setup_db();
        return new self($table);
    }

    /**
     * Set up the database connection used by the class.
     */
    protected static function _setup_db()
    {
        if (!is_object(self::$_db)) {
            $connection = self::$_config['orm.dsn'];
            $username = self::$_config['orm.username'];
            $password = self::$_config['orm.password'];
            $driver_options = self::$_config['orm.driver_options'];
            $db = new PDO($connection, $username, $password, $driver_options);
            $db->setAttribute(PDO::ATTR_ERRMODE, self::$_config['orm.error_mode']);

            self::setDb($db);
        }
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc). If this has been specified
     * manually using ORM::configure('orm.quote_character', 'some-char'),
     * this will do nothing.
     */
    public static function _setQuoteCharacter()
    {
        if (is_null(self::$_config['orm.quote_character'])) {
            self::$_config['orm.quote_character'] = self::_getQuoteCharacter();
        }
    }

    /**
     * Return the correct character used to quote identifiers (table
     * names, column names etc) by looking at the driver being used by PDO.
     */
    protected static function _getQuoteCharacter()
    {
        switch (self::$_db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                return '"';
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                return '`';
        }
    }

    /**
     * Returns the PDO instance used by the the ORM to communicate with
     * the database. This can be called if any low-level DB access is
     * required outside the class.
     */
    public static function getDb()
    {
        self::_setup_db(); // required in case this is called before ORM is instantiated
        return self::$_db;
    }

    /**
     * Set the PDO object used by ORM to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection.
     */
    public static function setDb($db)
    {
        self::$_db = $db;
        self::_setQuoteCharacter();
    }

    /**
     * Add a query to the internal query log. Only works if the
     * 'orm.logging' config option is set to true.
     *
     * This works by manually binding the parameters to the query - the
     * query isn't executed like this (PDO normally passes the query and
     * parameters to the database which takes care of the binding) but
     * doing it this way makes the logged queries more readable.
     */
    protected static function _logQuery($query, $parameters)
    {
        // If logging is not enabled, do nothing
        if (!self::$_config['orm.logging']) {
            return false;
        }

        if (count($parameters) > 0) {
            // Escape the parameters
            $parameters = array_map(array(self::$_db, 'quote'), $parameters);

            // Avoid %format collision for vsprintf
            $query = str_replace("%", "%%", $query);

            // Replace placeholders in the query for vsprintf
            if (false !== strpos($query, "'") || false !== strpos($query, '"')) {
                $query = ORMString::str_replace_outside_quotes("?", "%s", $query);
            } else {
                $query = str_replace("?", "%s", $query);
            }

            // Replace the question marks in the query with the parameters
            $bound_query = vsprintf($query, $parameters);
        } else {
            $bound_query = $query;
        }

        self::$_lastQuery = $bound_query;
        self::$_query_log[] = $bound_query;
        return true;
    }

    /**
     * Get the last query executed. Only works if the
     * 'orm.logging' config option is set to true. Otherwise
     * this will return null.
     */
    public static function get_lastQuery()
    {
        return self::$_lastQuery;
    }

    /**
     * Get an array containing all the queries run up to
     * now. Only works if the 'orm.logging' config option is
     * set to true. Otherwise returned array will be empty.
     */
    public static function get_query_log()
    {
        return self::$_query_log;
    }

    /**
     * "Private" constructor; shouldn't be called directly.
     * Use the ORM::table factory method instead.
     */
    protected function __construct($table, $data=array())
    {
        $this->_table = $table;
        $this->_data  = $data;
    }

    /**
     * Create a new, empty instance of the class. Used
     * to add a new row to your database. May optionally
     * be passed an associative array of data to populate
     * the instance. If so, all fields will be flagged as
     * dirty so all will be saved to the database when
     * save() is called.
     */
    public function create($data=null)
    {
        $this->_is_new = true;
        if (!is_null($data)) {
            return $this->hydrate($data)->force_all_dirty();
        }
        return $this;
    }

    /**
     * Specify the ID column to use for this instance or array of instances only.
     * This overrides the id_column and id_column_overrides settings.
     *
     * This is mostly useful for libraries built on top of ORM, and will
     * not normally be used in manually built queries. If you don't know why
     * you would want to use this, you should probably just ignore it.
     */
    public function use_id_column($id_column)
    {
        $this->_id_column = $id_column;
        return $this;
    }

    /**
     * Create an ORM instance from the given row (an associative
     * array of data fetched from the database)
     */
    protected function _create_instance_from_row($row)
    {
        $instance = self::table($this->_table);
        $instance->use_id_column($this->_id_column);
        $instance->hydrate($row);
        return $instance;
    }

    /**
     * Tell the ORM that you are expecting a single result
     * back from your query, and execute it. Will return
     * a single instance of the ORM class, or false if no
     * rows were returned.
     * As a shortcut, you may supply an ID as a parameter
     * to this method. This will perform a primary key
     * lookup on the table.
     */
    public function findOne($id=null)
    {
        if (!is_null($id)) {
            $this->whereIdIs($id);
        }
        $this->limit(1);
        $rows = $this->_run();

        if (empty($rows)) {
            return false;
        }

        return $this->_create_instance_from_row($rows[0]);
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * of instances of the ORM class, or an empty array if
     * no rows were returned.
     */
    public function findMany()
    {
        $rows = $this->_run();
        return array_map(array($this, '_create_instance_from_row'), $rows);
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array,
     * or an empty array if no rows were returned.
     * @return array
     */
    public function find()
    {
        return $this->_run();
    }

    /**
     * Execute an aggregate query on the current connection.
     * @param string $function The aggregate function to call eg. MIN, COUNT, etc
     * @param string $column The column to execute the aggregate query against
     * @return int
     */
    protected function _aggregateFunction($function, $column)
    {
        $function = strtoupper($function);
        $alias    = strtolower($function);
        if ('*' != $column) {
            $column = $this->_quote_identifier($column);
        }
        $this->selectExpr("$function($column)", $alias);
        $result = $this->findOne();
        return ($result !== false && isset($result->$alias)) ? (int) $result->$alias : 0;
    }

    /**
     * Tell the ORM that you wish to execute a COUNT query.
     * Will return an integer representing the number of
     * rows returned.
     */
    public function count($column = '*')
    {
        return $this->_aggregateFunction(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a MAX query.
     * Will return the max value of the choosen column.
     */
    public function max($column)
    {
        return $this->_aggregateFunction(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a MIN query.
     * Will return the min value of the choosen column.
     */
    public function min($column)
    {
        return $this->_aggregateFunction(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a SUM query.
     * Will return the sum of the choosen column.
     */
    public function sum($column)
    {
        return $this->_aggregateFunction(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a AVG query.
     * Will return the average value of the choosen column.
     */
    public function avg($column)
    {
        return $this->_aggregateFunction(__FUNCTION__, $column);
    }

    /**
    * This method can be called to hydrate (populate) this
    * instance of the class from an associative array of data.
    * This will usually be called only from inside the class,
    * but it's public in case you need to call it directly.
    */
    public function hydrate($data=array())
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * Force the ORM to flag all the fields in the $data array
     * as "dirty" and therefore update them when save() is called.
     */
    public function force_all_dirty()
    {
        $this->_dirty_fields = $this->_data;
        return $this;
    }

    /**
     * Perform a raw query. The query can contain placeholders in
     * either named or question mark style. If placeholders are
     * used, the parameters should be an array of values which will
     * be bound to the placeholders in the query. If this method
     * is called, all other query building methods will be ignored.
     */
    public function raw_query($query, $parameters = array())
    {
        $this->_is_raw_query = true;
        $this->_raw_query = $query;
        $this->_raw_parameters = $parameters;
        return $this;
    }

    /**
     * Add an alias for the main table to be used in SELECT queries
     */
    public function alias($alias)
    {
        $this->_alias = $alias;
        return $this;
    }

    /**
     * Internal method to add an unquoted expression to the set
     * of columns returned by the SELECT query. The second optional
     * argument is the alias to return the expression as.
     */
    protected function _add_result_column($expr, $alias=null)
    {
        if (!is_null($alias)) {
            $expr .= " AS " . $this->_quote_identifier($alias);
        }

        if ($this->_using_default_columns) {
            $this->_columns = array($expr);
            $this->_using_default_columns = false;
        } else {
            $this->_columns[] = $expr;
        }
        return $this;
    }

    /**
     * Add a column to the list of columns returned by the SELECT
     * query. This defaults to '*'. The second optional argument is
     * the alias to return the column as.
     */
    public function select($column, $alias=null)
    {
        $column = $this->_quote_identifier($column);
        return $this->_add_result_column($column, $alias);
    }

    /**
     * Add an unquoted expression to the list of columns returned
     * by the SELECT query. The second optional argument is
     * the alias to return the column as.
     */
    public function selectExpr($expr, $alias=null)
    {
        return $this->_add_result_column($expr, $alias);
    }

    /**
     * Add columns to the list of columns returned by the SELECT
     * query. This defaults to '*'. Many columns can be supplied
     * as either an array or as a list of parameters to the method.
     *
     * Note that the alias must not be numeric - if you want a
     * numeric alias then prepend it with some alpha chars. eg. a1
     *
     * @example selectMany(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5');
     * @example selectMany('column', 'column2', 'column3');
     * @example selectMany(array('column', 'column2', 'column3'), 'column4', 'column5');
     *
     * @return \ORM
     */
    public function selectMany()
    {
        $columns = func_get_args();
        if (!empty($columns)) {
            $columns = $this->_normalise_selectMany_columns($columns);
            foreach ($columns as $alias => $column) {
                if (is_numeric($alias)) {
                    $alias = null;
                }
                $this->select($column, $alias);
            }
        }
        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns returned
     * by the SELECT query. Many columns can be supplied as either
     * an array or as a list of parameters to the method.
     *
     * Note that the alias must not be numeric - if you want a
     * numeric alias then prepend it with some alpha chars. eg. a1
     *
     * @example selectMany_expr(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5')
     * @example selectMany_expr('column', 'column2', 'column3')
     * @example selectMany_expr(array('column', 'column2', 'column3'), 'column4', 'column5')
     *
     * @return \ORM
     */
    public function selectMany_expr()
    {
        $columns = func_get_args();
        if (!empty($columns)) {
            $columns = $this->_normalise_selectMany_columns($columns);
            foreach ($columns as $alias => $column) {
                if (is_numeric($alias)) {
                    $alias = null;
                }
                $this->selectExpr($column, $alias);
            }
        }
        return $this;
    }

    /**
     * Take a column specification for the select many methods and convert it
     * into a normalised array of columns and aliases.
     *
     * It is designed to turn the following styles into a normalised array:
     *
     * array(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5'))
     *
     * @param array $columns
     * @return array
     */
    protected function _normalise_selectMany_columns($columns)
    {
        $return = array();
        foreach ($columns as $column) {
            if (is_array($column)) {
                foreach ($column as $key => $value) {
                    if (!is_numeric($key)) {
                        $return[$key] = $value;
                    } else {
                        $return[] = $value;
                    }
                }
            } else {
                $return[] = $column;
            }
        }
        return $return;
    }

    /**
     * Add a DISTINCT keyword before the list of columns in the SELECT query
     */
    public function distinct()
    {
        $this->_distinct = true;
        return $this;
    }

    /**
     * Internal method to add a JOIN source to the query.
     *
     * The join_operator should be one of INNER, LEFT OUTER, CROSS etc - this
     * will be prepended to JOIN.
     *
     * The table should be the name of the table to join to.
     *
     * The constraint may be either a string or an array with three elements. If it
     * is a string, it will be compiled into the query as-is, with no escaping. The
     * recommended way to supply the constraint is as an array with three elements:
     *
     * first_column, operator, second_column
     *
     * Example: array('user.id', '=', 'profile.user_id')
     *
     * will compile to
     *
     * ON `user`.`id` = `profile`.`user_id`
     *
     * The final (optional) argument specifies an alias for the joined table.
     */
    protected function _addJoin($join_operator, $table, $constraint, $alias=null)
    {
        $join_operator = trim("{$join_operator} JOIN");

        $table = $this->_quote_identifier($table);

        // Add table alias if present
        if (!is_null($alias)) {
            $alias = $this->_quote_identifier($alias);
            $table .= " {$alias}";
        }

        // Build the constraint
        if (is_array($constraint)) {
            list($first_column, $operator, $second_column) = $constraint;
            $first_column = $this->_quote_identifier($first_column);
            $second_column = $this->_quote_identifier($second_column);
            $constraint = "{$first_column} {$operator} {$second_column}";
        }

        $this->_joins[] = "{$join_operator} {$table} ON {$constraint}";
        return $this;
    }

    /**
     * Add a simple JOIN source to the query
     */
    public function join($table, $constraint, $alias=null)
    {
        return $this->_addJoin("", $table, $constraint, $alias);
    }

    /**
     * Add an INNER JOIN souce to the query
     */
    public function innerJoin($table, $constraint, $alias=null)
    {
        return $this->_addJoin("INNER", $table, $constraint, $alias);
    }

    /**
     * Add a LEFT OUTER JOIN souce to the query
     */
    public function leftJoin($table, $constraint, $alias=null)
    {
        return $this->_addJoin("LEFT OUTER", $table, $constraint, $alias);
    }

    /**
     * Add an RIGHT OUTER JOIN souce to the query
     */
    public function rightJoin($table, $constraint, $alias=null)
    {
        return $this->_addJoin("RIGHT OUTER", $table, $constraint, $alias);
    }

    /**
     * Add an FULL OUTER JOIN souce to the query
     */
    public function fullJoin($table, $constraint, $alias=null)
    {
        return $this->_addJoin("FULL OUTER", $table, $constraint, $alias);
    }

    /**
     * Internal method to add a WHERE condition to the query
     */
    protected function _addWhere($fragment, $values=array())
    {
        if (!is_array($values)) {
            $values = array($values);
        }
        $this->_where[] = array(
            self::WHERE_FRAGMENT => $fragment,
            self::WHERE_VALUES => $values,
        );
        return $this;
    }

    /**
     * Helper method to compile a simple COLUMN SEPARATOR VALUE
     * style WHERE condition into a string and value ready to
     * be passed to the _addWhere method. Avoids duplication
     * of the call to _quote_identifier
     */
    protected function _addSimpleWhere($column_name, $separator, $value)
    {
        // Add the table name in case of ambiguous columns
        if (count($this->_joins) > 0 && strpos($column_name, '.') === false) {
            $column_name = "{$this->_table}.{$column_name}";
        }
        $column_name = $this->_quote_identifier($column_name);
        return $this->_addWhere("{$column_name} {$separator} ?", $value);
    }

    /**
     * Return a string containing the given number of question marks,
     * separated by commas. Eg "?, ?, ?"
     */
    protected function _createPlaceholders($fields)
    {
        if (!empty($fields)) {
            $db_fields = array();
            foreach ($fields as $key => $value) {
                // Process expression fields directly into the query
                if (array_key_exists($key, $this->_expr_fields)) {
                    $db_fields[] = $value;
                } else {
                    $db_fields[] = '?';
                }
            }
            return implode(', ', $db_fields);
        }
    }

    /**
     * Add a WHERE column = value clause to your query. Each time
     * this is called in the chain, an additional WHERE will be
     * added, and these will be ANDed together when the final query
     * is built.
     */
    public function where($column_name, $value)
    {
        return $this->whereEqual($column_name, $value);
    }

    /**
     * More explicitly named version of for the where() method.
     * Can be used if preferred.
     */
    public function whereEqual($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '=', $value);
    }

    /**
     * Add a WHERE column != value clause to your query.
     */
    public function whereNotEqual($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '!=', $value);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function whereIdIs($id)
    {
        return $this->where($this->_get_id_column_name(), $id);
    }

    /**
     * Add a WHERE ... LIKE clause to your query.
     */
    public function whereLike($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, 'LIKE', $value);
    }

    /**
     * Add where WHERE ... NOT LIKE clause to your query.
     */
    public function whereNotLike($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, 'NOT LIKE', $value);
    }

    /**
     * Add a WHERE ... > clause to your query
     */
    public function whereGt($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '>', $value);
    }

    /**
     * Add a WHERE ... < clause to your query
     */
    public function whereLt($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '<', $value);
    }

    /**
     * Add a WHERE ... >= clause to your query
     */
    public function whereGte($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '>=', $value);
    }

    /**
     * Add a WHERE ... <= clause to your query
     */
    public function whereLte($column_name, $value)
    {
        return $this->_addSimpleWhere($column_name, '<=', $value);
    }

    /**
     * Add a WHERE ... IN clause to your query
     */
    public function whereIn($column_name, $values)
    {
        $column_name = $this->_quote_identifier($column_name);
        $placeholders = $this->_createPlaceholders($values);
        return $this->_addWhere("{$column_name} IN ({$placeholders})", $values);
    }

    /**
     * Add a WHERE ... NOT IN clause to your query
     */
    public function whereNotIn($column_name, $values)
    {
        $column_name = $this->_quote_identifier($column_name);
        $placeholders = $this->_createPlaceholders($values);
        return $this->_addWhere("{$column_name} NOT IN ({$placeholders})", $values);
    }

    /**
     * Add a WHERE column IS NULL clause to your query
     */
    public function whereNull($column_name)
    {
        $column_name = $this->_quote_identifier($column_name);
        return $this->_addWhere("{$column_name} IS NULL");
    }

    /**
     * Add a WHERE column IS NOT NULL clause to your query
     */
    public function whereNotNull($column_name)
    {
        $column_name = $this->_quote_identifier($column_name);
        return $this->_addWhere("{$column_name} IS NOT NULL");
    }

    /**
     * Add a raw WHERE clause to the query. The clause should
     * contain question mark placeholders, which will be bound
     * to the parameters supplied in the second argument.
     */
    public function whereRaw($clause, $parameters=array())
    {
        return $this->_addWhere($clause, $parameters);
    }

    /**
     * Add a LIMIT to the query
     */
    public function limit($limit)
    {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Add an OFFSET to the query
     */
    public function offset($offset)
    {
        $this->_offset = $offset;
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query
     */
    protected function _addOrderBy($column_name, $ordering)
    {
        $column_name = $this->_quote_identifier($column_name);
        $this->_orderBy[] = "{$column_name} {$ordering}";
        return $this;
    }

    /**
     * Add an ORDER BY column DESC clause
     */
    public function orderByDESC($column_name)
    {
        return $this->_addOrderBy($column_name, 'DESC');
    }

    /**
     * Add an ORDER BY column ASC clause
     */
    public function orderByASC($column_name)
    {
        return $this->_addOrderBy($column_name, 'ASC');
    }

    /**
     * Add an unquoted expression as an ORDER BY clause
     */
    public function orderByExpr($clause)
    {
        $this->_orderBy[] = $clause;
        return $this;
    }

    /**
     * Add a column to the list of columns to GROUP BY
     */
    public function groupBy($column_name)
    {
        $column_name = $this->_quote_identifier($column_name);
        $this->_groupBy[] = $column_name;
        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns to GROUP BY
     */
    public function groupByExpr($expr)
    {
        $this->_groupBy[] = $expr;
        return $this;
    }

    /**
     * Build a SELECT statement based on the clauses that have
     * been passed to this instance by chaining method calls.
     */
    protected function _buildSelect()
    {
        // If the query is raw, just set the $this->_values to be
        // the raw query parameters and return the raw query
        if ($this->_is_raw_query) {
            $this->_values = $this->_raw_parameters;
            return $this->_raw_query;
        }

        // Build and return the full SELECT statement by concatenating
        // the results of calling each separate builder method.
        return $this->_join_if_not_empty(" ", array(
            $this->_buildSelect_start(),
            $this->_buildJoin(),
            $this->_buildWhere(),
            $this->_buildGroupBy(),
            $this->_buildOrderBy(),
            $this->_buildLimit(),
            $this->_buildOffset(),
        ));
    }

    /**
     * Build the start of the SELECT statement
     */
    protected function _buildSelect_start()
    {
        $result_columns = join(', ', $this->_columns);

        if ($this->_distinct) {
            $result_columns = 'DISTINCT ' . $result_columns;
        }

        $fragment = "SELECT {$result_columns} FROM " . $this->_quote_identifier($this->_table);

        if (!is_null($this->_alias)) {
            $fragment .= " " . $this->_quote_identifier($this->_alias);
        }
        return $fragment;
    }

    /**
     * Build the JOIN sources
     */
    protected function _buildJoin()
    {
        if (count($this->_joins) === 0) {
            return '';
        }

        return join(" ", $this->_joins);
    }

    /**
     * Build the WHERE clause(s)
     */
    protected function _buildWhere()
    {
        // If there are no WHERE clauses, return empty string
        if (count($this->_where) === 0) {
            return '';
        }

        $where_conditions = array();
        foreach ($this->_where as $condition) {
            $where_conditions[] = $condition[self::WHERE_FRAGMENT];
            $this->_values = array_merge($this->_values, $condition[self::WHERE_VALUES]);
        }

        return "WHERE " . join(" AND ", $where_conditions);
    }

    /**
     * Build GROUP BY
     */
    protected function _buildGroupBy()
    {
        if (count($this->_groupBy) === 0) {
            return '';
        }
        return "GROUP BY " . join(", ", $this->_groupBy);
    }

    /**
     * Build ORDER BY
     */
    protected function _buildOrderBy()
    {
        if (count($this->_orderBy) === 0) {
            return '';
        }
        return "ORDER BY " . join(", ", $this->_orderBy);
    }

    /**
     * Build LIMIT
     */
    protected function _buildLimit()
    {
        if (!is_null($this->_limit)) {
            return "LIMIT " . $this->_limit;
        }
        return '';
    }

    /**
     * Build OFFSET
     */
    protected function _buildOffset()
    {
        if (!is_null($this->_offset)) {
            return "OFFSET " . $this->_offset;
        }
        return '';
    }

    /**
     * Wrapper around PHP's join function which
     * only adds the pieces if they are not empty.
     */
    protected function _join_if_not_empty($glue, $pieces)
    {
        $filtered_pieces = array();
        foreach ($pieces as $piece) {
            if (is_string($piece)) {
                $piece = trim($piece);
            }
            if (!empty($piece)) {
                $filtered_pieces[] = $piece;
            }
        }
        return join($glue, $filtered_pieces);
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    protected function _quote_identifier($identifier)
    {
        $parts = explode('.', $identifier);
        $parts = array_map(array($this, '_quote_identifier_part'), $parts);
        return join('.', $parts);
    }

    /**
     * This method performs the actual quoting of a single
     * part of an identifier, using the identifier quote
     * character specified in the config (or autodetected).
     */
    protected function _quote_identifier_part($part)
    {
        if ($part === '*') {
            return $part;
        }
        $quote_character = self::$_config['orm.quote_character'];
        return $quote_character . $part . $quote_character;
    }

    /**
     * Create a cache key for the given query and parameters.
     */
    protected static function _create_cache_key($query, $parameters)
    {
        $parameter_string = join(',', $parameters);
        $key = $query . ':' . $parameter_string;
        return sha1($key);
    }

    /**
     * Check the query cache for the given cache key. If a value
     * is cached for the key, return the value. Otherwise, return false.
     */
    protected static function _check_query_cache($cache_key)
    {
        if (isset(self::$_query_cache[$cache_key])) {
            return self::$_query_cache[$cache_key];
        }
        return false;
    }

    /**
     * Clear the query cache
     */
    public static function clear_cache()
    {
        self::$_query_cache = array();
    }

    /**
     * Add the given value to the query cache.
     */
    protected static function _cache_query_result($cache_key, $value)
    {
        self::$_query_cache[$cache_key] = $value;
    }

    /**
     * Execute the SELECT query that has been built up by chaining methods
     * on this class. Return an array of rows as associative arrays.
     */
    protected function _run()
    {
        $query = $this->_buildSelect();
        $caching_enabled = self::$_config['orm.caching'];

        if ($caching_enabled) {
            $cache_key = self::_create_cache_key($query, $this->_values);
            $cached_result = self::_check_query_cache($cache_key);

            if ($cached_result !== false) {
                return $cached_result;
            }
        }

        self::_logQuery($query, $this->_values);
        $statement = self::$_db->prepare($query);
        $statement->execute($this->_values);

        $rows = array();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        if ($caching_enabled) {
            self::_cache_query_result($cache_key, $rows);
        }

        return $rows;
    }

    /**
     * Return the raw data wrapped by this ORM
     * instance as an associative array. Column
     * names may optionally be supplied as arguments,
     * if so, only those keys will be returned.
     */
    public function toArray()
    {
        if (func_num_args() === 0) {
            return $this->_data;
        }
        $args = func_get_args();
        return array_intersect_key($this->_data, array_flip($args));
    }

    /**
     * Return the value of a property of this object (database row)
     * or null if not present.
     */
    public function get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    /**
     * Return the name of the column in the database table which contains
     * the primary key ID of the row.
     */
    protected function _get_id_column_name()
    {
        if (!is_null($this->_id_column)) {
            return $this->_id_column;
        }
        if (isset(self::$_config['orm.id_overrides'][$this->_table])) {
            return self::$_config['orm.id_overrides'][$this->_table];
        } else {
            return self::$_config['orm.id_column'];
        }
    }

    /**
     * Get the primary key ID of this object.
     */
    public function id()
    {
        return $this->get($this->_get_id_column_name());
    }

    /**
     * Set a property to a particular value on this object.
     * To set multiple properties at once, pass an associative array
     * as the first parameter and leave out the second parameter.
     * Flags the properties as 'dirty' so they will be saved to the
     * database when save() is called.
     */
    public function set($key, $value = null)
    {
        $this->_setProperty($key, $value);
    }

    public function set_expr($key, $value = null)
    {
        $this->_setProperty($key, $value, true);
    }

    /**
     * Set a property on the ORM object.
     * @param string|array $key
     * @param string|null $value
     * @param bool $raw Whether this value should be treated as raw or not
     */
    protected function _setProperty($key, $value = null, $expr = false)
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        foreach ($key as $field => $value) {
            $this->_data[$field] = $value;
            $this->_dirty_fields[$field] = $value;
            if (false === $expr and isset($this->_expr_fields[$field])) {
                unset($this->_expr_fields[$field]);
            } elseif (true === $expr) {
                $this->_expr_fields[$field] = true;
            }
        }
    }

    /**
     * Check whether the given field has been changed since this
     * object was saved.
     */
    public function hasChanged($key)
    {
        return isset($this->_dirty_fields[$key]);
    }

    /**
     * Save any fields which have been modified on this object
     * to the database.
     */
    public function save()
    {
        $query = array();

        // remove any expression fields as they are already baked into the query
        $values = array_values(array_diff_key($this->_dirty_fields, $this->_expr_fields));

        if (!$this->_is_new) { // UPDATE
            // If there are no dirty values, do nothing
            if (count($values) == 0) {
                return true;
            }
            $query = $this->_build_update();
            $values[] = $this->id();
        } else { // INSERT
            $query = $this->_build_insert();
        }

        self::_logQuery($query, $values);
        $statement = self::$_db->prepare($query);
        $success = $statement->execute($values);

        // If we've just inserted a new record, set the ID of this object
        if ($this->_is_new) {
            $this->_is_new = false;
            if (is_null($this->id())) {
                $this->_data[$this->_get_id_column_name()] = self::$_db->lastInsertId();
            }
        }

        $this->_dirty_fields = array();
        return $success;
    }

    /**
     * Build an UPDATE query
     */
    protected function _build_update()
    {
        $query = array();
        $query[] = "UPDATE {$this->_quote_identifier($this->_table)} SET";

        $field_list = array();
        foreach ($this->_dirty_fields as $key => $value) {
            if (!array_key_exists($key, $this->_expr_fields)) {
                $value = '?';
            }
            $field_list[] = "{$this->_quote_identifier($key)} = $value";
        }
        $query[] = join(", ", $field_list);
        $query[] = "WHERE";
        $query[] = $this->_quote_identifier($this->_get_id_column_name());
        $query[] = "= ?";
        return join(" ", $query);
    }

    /**
     * Build an INSERT query
     */
    protected function _build_insert()
    {
        $query[] = "INSERT INTO";
        $query[] = $this->_quote_identifier($this->_table);
        $field_list = array_map(array($this, '_quote_identifier'), array_keys($this->_dirty_fields));
        $query[] = "(" . join(", ", $field_list) . ")";
        $query[] = "VALUES";

        $placeholders = $this->_createPlaceholders($this->_dirty_fields);
        $query[] = "({$placeholders})";
        return join(" ", $query);
    }

    /**
     * Delete this record from the database
     */
    public function delete()
    {
        $query = join(" ", array(
            "DELETE FROM",
            $this->_quote_identifier($this->_table),
            "WHERE",
            $this->_quote_identifier($this->_get_id_column_name()),
            "= ?",
        ));
        $params = array($this->id());
        self::_logQuery($query, $params);
        $statement = self::$_db->prepare($query);
        return $statement->execute($params);
    }

    /**
     * Delete many records from the database
     */
    public function deleteMany()
    {
        // Build and return the full DELETE statement by concatenating
        // the results of calling each separate builder method.
        $query = $this->_join_if_not_empty(" ", array(
            "DELETE FROM",
            $this->_quote_identifier($this->_table),
            $this->_buildWhere(),
        ));
        $statement = self::$_db->prepare($query);
        return $statement->execute($this->_values);
    }

    // --------------------- //
    // --- MAGIC METHODS --- //
    // --------------------- //
    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __unset($key)
    {
        unset($this->_data[$key]);
        unset($this->_dirty_fields[$key]);
    }


    public function __isset($key)
    {
        return isset($this->_data[$key]);
    }
}

/**
 * A class to handle str_replace operations that involve quoted strings
 * @example ORMString::str_replace_outside_quotes('?', '%s', 'columnA = "Hello?" AND columnB = ?');
 * @example ORMString::value('columnA = "Hello?" AND columnB = ?')->replace_outside_quotes('?', '%s');
 * @author Jeff Roberson <ridgerunner@fluxbb.org>
 * @author Simon Holywell <treffynnon@php.net>
 * @link http://stackoverflow.com/a/13370709/461813 StackOverflow answer
 */
class ORMString
{
    protected $subject;
    protected $search;
    protected $replace;

    /**
     * Get an easy to use instance of the class
     * @param string $subject
     * @return \self
     */
    public static function value($subject)
    {
        return new self($subject);
    }

    /**
     * Shortcut method: Replace all occurrences of the search string with the replacement
     * string where they appear outside quotes.
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function str_replace_outside_quotes($search, $replace, $subject)
    {
        return self::value($subject)->replace_outside_quotes($search, $replace);
    }

    /**
     * Set the base string object
     * @param string $subject
     */
    public function __construct($subject)
    {
        $this->subject = (string) $subject;
    }

    /**
     * Replace all occurrences of the search string with the replacement
     * string where they appear outside quotes
     * @param string $search
     * @param string $replace
     * @return string
     */
    public function replace_outside_quotes($search, $replace)
    {
        $this->search = $search;
        $this->replace = $replace;
        return $this->_str_replace_outside_quotes();
    }

    /**
     * Validate an input string and perform a replace on all ocurrences
     * of $this->search with $this->replace
     * @author Jeff Roberson <ridgerunner@fluxbb.org>
     * @link http://stackoverflow.com/a/13370709/461813 StackOverflow answer
     * @return string
     */
    protected function _str_replace_outside_quotes()
    {
        $re_valid = '/
            # Validate string having embedded quoted substrings.
            ^                           # Anchor to start of string.
            (?:                         # Zero or more string chunks.
              "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
            | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk,
            | [^\'"\\\\]+               # or an unquoted chunk (no escapes).
            )*                          # Zero or more string chunks.
            \z                          # Anchor to end of string.
            /sx';
        if (!preg_match($re_valid, $this->subject)) {
            throw new ORMStringException("Subject string is not valid in the replace_outside_quotes context.");
        }
        $re_parse = '/
            # Match one chunk of a valid string having embedded quoted substrings.
              (                         # Either $1: Quoted chunk.
                "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
              | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk.
              )                         # End $1: Quoted chunk.
            | ([^\'"\\\\]+)             # or $2: an unquoted chunk (no escapes).
            /sx';
        return preg_replace_callback($re_parse, array($this, '_str_replace_outside_quotes_cb'), $this->subject);
    }

    /**
     * Process each matching chunk from preg_replace_callback replacing
     * each occurrence of $this->search with $this->replace
     * @author Jeff Roberson <ridgerunner@fluxbb.org>
     * @link http://stackoverflow.com/a/13370709/461813 StackOverflow answer
     * @param array $matches
     * @return string
     */
    protected function _str_replace_outside_quotes_cb($matches)
    {
        // Return quoted string chunks (in group $1) unaltered.
        if ($matches[1]) {
            return $matches[1];
        }
        // Process only unquoted chunks (in group $2).
        return preg_replace(
            '/'. preg_quote($this->search, '/') .'/',
            $this->replace,
            $matches[2]
        );
    }
}

/**
 * A placeholder for exceptions eminating from the ORMString class
 */
class ORMStringException extends Exception
{
}
