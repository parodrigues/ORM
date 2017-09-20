<?php

class ORM
{
    protected function __construct($table, $data=array())
    public function __get($key)
    public function __set($key, $value)
    public function __unset($key)
    public function __isset($key)

    protected static function _logQuery($query, $parameters)
    protected static function _setup_db()

    protected function _create_instance_from_row($row)

    protected function _addWhere($fragment, $values=array())
    protected function _addSimpleWhere($column, $separator, $value)
    protected function _addOrderBy($column, $ordering)
    protected function _addJoin($join_operator, $table, $constraint, $alias=null)
    protected function _createPlaceholders($fields)

    protected function _add_result_column($expr, $alias=null)
    protected function _normalise_selectMany_columns($columns)
    protected function _aggregateFunction($function, $column)

    protected static function _getQuoteCharacter()

    public static function _setQuoteCharacter()

    public static function getDb()
    public static function setDb($db)
    public static function get_lastQuery()
    public static function get_query_log()
    public static function configure($key, $value=null)
    public static function table($table)

    protected function _run()
    protected function _get_id_column()

    public function id()
    public function toArray()
    public function get($key)
    public function set($key, $value = null)
    public function set_expr($key, $value = null)

    protected function _setProperty($key, $value = null, $expr = false)

    public function create($data=null)
    public function use_id_column($id_column)

    public function hasChanged($key)
    public function save()
    public function delete()
    public function deleteMany()

    public function hydrate($data=array())
    public function force_all_dirty()
    public function raw_query($query, $parameters = array())
    public function alias($alias)

    public function select($column, $alias=null)
    public function selectExpr($expr, $alias=null)
    public function selectMany()
    public function selectMany_expr()

    public function distinct()

    public function count($column = '*')
    public function max($column)
    public function min($column)
    public function sum($column)
    public function avg($column)

    public function orderByASC($column)
    public function orderByDESC($column)
    public function orderByExpr($clause)

    public function groupBy($column)
    public function groupByExpr($expr)

    public function join($table, $constraint, $alias=null)
    public function innerJoin($table, $constraint, $alias=null)
    public function rightJoin($table, $constraint, $alias=null)
    public function leftJoin($table, $constraint, $alias=null)
    public function fullJoin($table, $constraint, $alias=null)

    public function where($column, $value)

    public function whereEqual($column, $value)
    public function whereNotEqual($column, $value)

    public function whereNull($column)
    public function whereNotNull($column)

    public function whereLike($column, $value)
    public function whereNotLike($column, $value)

    public function whereIn($column, $values)
    public function whereNotIn($column, $values)

    public function whereGt($column, $value)
    public function whereGte($column, $value)
    public function whereLt($column, $value)
    public function whereLte($column, $value)

    public function whereIdIs($id)
    public function whereRaw($clause, $parameters=array())

    public function limit($limit)
    public function offset($offset)

    public function find()
    public function findOne($id=null)
    public function findMany()

    protected function _buildSelect()
    protected function _buildSelect_start()
    protected function _buildJoin()
    protected function _buildWhere()
    protected function _buildGroupBy()
    protected function _buildOrderBy()
    protected function _buildLimit()
    protected function _buildOffset()
    protected function _join_if_not_empty($glue, $pieces)
    protected function _quote_identifier($identifier)
    protected function _quote_identifier_part($part)
    protected function _build_update()
    protected function _build_insert()

    protected static function _create_cache_key($query, $parameters)
    protected static function _check_query_cache($cache_key)
    protected static function _cache_query_result($cache_key, $value)

    public static function clear_cache()

    const WHERE_FRAGMENT = 0;
    const WHERE_VALUES = 1;

    protected static $_config = array();
    protected static $_db;
    protected static $_lastQuery;
    protected static $_query_log = array();
    protected static $_query_cache = array();

    protected $_data = array();
    protected $_table;
    protected $_alias = null;
    protected $_values = array();
    protected $_columns = array('*');
    protected $_using_default_columns = true;
    protected $_joins = array();
    protected $_distinct = false;
    protected $_is_raw_query = false;
    protected $_raw_query = '';
    protected $_raw_parameters = array();
    protected $_where = array();
    protected $_limit = null;
    protected $_offset = null;
    protected $_orderBy = array();
    protected $_groupBy = array();
    protected $_dirty_fields = array();
    protected $_expr_fields = array();
    protected $_is_new = false;
    protected $_id_column = null;
}

class ORMString
{
    public function __construct($subject)
    public function replace_outside_quotes($search, $replace)
    protected function _str_replace_outside_quotes()
    protected function _str_replace_outside_quotes_cb($matches)
    public static function str_replace_outside_quotes($search, $replace, $subject)
}