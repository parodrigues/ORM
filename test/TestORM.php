<?php
/*
 * Basic testing for ORM
 *
 * Checks that the generated SQL is correct
 *
 */

require_once dirname(__FILE__) . "/../ORM.php";
require_once dirname(__FILE__) . "/Classes.php";

// Enable logging
ORM::configure('orm.logging', true);

// Set up the dummy database connection
$db = new DummyPDO('sqlite::memory:');
ORM::setDb($db);

ORM::table('widget')->findMany();
$expected = "SELECT * FROM `widget`";
Tester::check_equal("Basic unfiltered findMany query", $expected);

ORM::table('widget')->findOne();
$expected = "SELECT * FROM `widget` LIMIT 1";
Tester::check_equal("Basic unfiltered findOne query", $expected);

ORM::table('widget')->whereIdIs(5)->findOne();
$expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
Tester::check_equal("whereIdIs method", $expected);

ORM::table('widget')->findOne(5);
$expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
Tester::check_equal("Filtering on ID passed into findOne method", $expected);

ORM::table('widget')->count();
$expected = "SELECT COUNT(*) AS `count` FROM `widget` LIMIT 1";
Tester::check_equal("COUNT query", $expected);

ORM::table('person')->max('height');
$expected = "SELECT MAX(`height`) AS `max` FROM `person` LIMIT 1";
Tester::check_equal("MAX query", $expected);

ORM::table('person')->min('height');
$expected = "SELECT MIN(`height`) AS `min` FROM `person` LIMIT 1";
Tester::check_equal("MIN query", $expected);

ORM::table('person')->avg('height');
$expected = "SELECT AVG(`height`) AS `avg` FROM `person` LIMIT 1";
Tester::check_equal("AVG query", $expected);

ORM::table('person')->sum('height');
$expected = "SELECT SUM(`height`) AS `sum` FROM `person` LIMIT 1";
Tester::check_equal("SUM query", $expected);

ORM::table('widget')->where('name', 'Fred')->findOne();
$expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
Tester::check_equal("Single where clause", $expected);

ORM::table('widget')->where('name', 'Fred')->where('age', 10)->findOne();
$expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
Tester::check_equal("Multiple WHERE clauses", $expected);

ORM::table('widget')->whereNotEqual('name', 'Fred')->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` != 'Fred'";
Tester::check_equal("whereNotEqual method", $expected);

ORM::table('widget')->whereLike('name', '%Fred%')->findOne();
$expected = "SELECT * FROM `widget` WHERE `name` LIKE '%Fred%' LIMIT 1";
Tester::check_equal("whereLike method", $expected);

ORM::table('widget')->whereNotLike('name', '%Fred%')->findOne();
$expected = "SELECT * FROM `widget` WHERE `name` NOT LIKE '%Fred%' LIMIT 1";
Tester::check_equal("whereNotLike method", $expected);

ORM::table('widget')->whereIn('name', array('Fred', 'Joe'))->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')";
Tester::check_equal("whereIn method", $expected);

ORM::table('widget')->whereNotIn('name', array('Fred', 'Joe'))->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')";
Tester::check_equal("whereNotIn method", $expected);

ORM::table('widget')->limit(5)->findMany();
$expected = "SELECT * FROM `widget` LIMIT 5";
Tester::check_equal("LIMIT clause", $expected);

ORM::table('widget')->limit(5)->offset(5)->findMany();
$expected = "SELECT * FROM `widget` LIMIT 5 OFFSET 5";
Tester::check_equal("LIMIT and OFFSET clause", $expected);

ORM::table('widget')->orderByDESC('name')->findOne();
$expected = "SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1";
Tester::check_equal("ORDER BY DESC", $expected);

ORM::table('widget')->orderByASC('name')->findOne();
$expected = "SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1";
Tester::check_equal("ORDER BY ASC", $expected);

ORM::table('widget')->orderByExpr('SOUNDEX(`name`)')->findOne();
$expected = "SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1";
Tester::check_equal("ORDER BY expression", $expected);

ORM::table('widget')->orderByASC('name')->orderByDESC('age')->findOne();
$expected = "SELECT * FROM `widget` ORDER BY `name` ASC, `age` DESC LIMIT 1";
Tester::check_equal("Multiple ORDER BY", $expected);

ORM::table('widget')->groupBy('name')->findMany();
$expected = "SELECT * FROM `widget` GROUP BY `name`";
Tester::check_equal("GROUP BY", $expected);

ORM::table('widget')->groupBy('name')->groupBy('age')->findMany();
$expected = "SELECT * FROM `widget` GROUP BY `name`, `age`";
Tester::check_equal("Multiple GROUP BY", $expected);

ORM::table('widget')->groupByExpr("FROM_UNIXTIME(`time`, '%Y-%m')")->findMany();
$expected = "SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, '%Y-%m')";
Tester::check_equal("GROUP BY expression", $expected);

ORM::table('widget')->where('name', 'Fred')->limit(5)->offset(5)->orderByASC('name')->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' ORDER BY `name` ASC LIMIT 5 OFFSET 5";
Tester::check_equal("Complex query", $expected);

ORM::table('widget')->whereLt('age', 10)->whereGt('age', 5)->findMany();
$expected = "SELECT * FROM `widget` WHERE `age` < '10' AND `age` > '5'";
Tester::check_equal("Less than and greater than", $expected);

ORM::table('widget')->whereLte('age', 10)->whereGte('age', 5)->findMany();
$expected = "SELECT * FROM `widget` WHERE `age` <= '10' AND `age` >= '5'";
Tester::check_equal("Less than or equal and greater than or equal", $expected);

ORM::table('widget')->whereNull('name')->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` IS NULL";
Tester::check_equal("whereNull method", $expected);

ORM::table('widget')->whereNotNull('name')->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` IS NOT NULL";
Tester::check_equal("whereNotNull method", $expected);

ORM::table('widget')->whereRaw('`name` = ? AND (`age` = ? OR `age` = ?)', array('Fred', 5, 10))->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
Tester::check_equal("Raw WHERE clause", $expected);

ORM::table('widget')->whereRaw('STRFTIME("%Y", "now") = ?', array(2012))->findMany();
$expected = "SELECT * FROM `widget` WHERE STRFTIME(\"%Y\", \"now\") = '2012'";
Tester::check_equal("Raw WHERE clause with '%'", $expected);

ORM::table('widget')->whereRaw('`name` = "Fred"')->findMany();
$expected = "SELECT * FROM `widget` WHERE `name` = \"Fred\"";
Tester::check_equal("Raw WHERE clause with no parameters", $expected);

ORM::table('widget')->where('age', 18)->whereRaw('(`name` = ? OR `name` = ?)', array('Fred', 'Bob'))->where('size', 'large')->findMany();
$expected = "SELECT * FROM `widget` WHERE `age` = '18' AND (`name` = 'Fred' OR `name` = 'Bob') AND `size` = 'large'";
Tester::check_equal("Raw WHERE clause in method chain", $expected);

ORM::table('widget')->raw_query('SELECT `w`.* FROM `widget` w')->findMany();
$expected = "SELECT `w`.* FROM `widget` w";
Tester::check_equal("Raw query", $expected);

ORM::table('widget')->raw_query('SELECT `w`.* FROM `widget` w WHERE `name` = ? AND `age` = ?', array('Fred', 5))->findMany();
$expected = "SELECT `w`.* FROM `widget` w WHERE `name` = 'Fred' AND `age` = '5'";
Tester::check_equal("Raw query with parameters", $expected);

ORM::table('widget')->select('name')->findMany();
$expected = "SELECT `name` FROM `widget`";
Tester::check_equal("Simple result column", $expected);

ORM::table('widget')->select('name')->select('age')->findMany();
$expected = "SELECT `name`, `age` FROM `widget`";
Tester::check_equal("Multiple simple result columns", $expected);

ORM::table('widget')->select('widget.name')->findMany();
$expected = "SELECT `widget`.`name` FROM `widget`";
Tester::check_equal("Specify table name and column in result columns", $expected);

ORM::table('widget')->select('widget.name', 'widget_name')->findMany();
$expected = "SELECT `widget`.`name` AS `widget_name` FROM `widget`";
Tester::check_equal("Aliases in result columns", $expected);

ORM::table('widget')->selectExpr('COUNT(*)', 'count')->findMany();
$expected = "SELECT COUNT(*) AS `count` FROM `widget`";
Tester::check_equal("Literal expression in result columns", $expected);

ORM::table('widget')->selectMany(array('widget_name' => 'widget.name'), 'widget_handle')->findMany();
$expected = "SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`";
Tester::check_equal("Aliases in select many result columns", $expected);

ORM::table('widget')->selectMany_expr(array('count' => 'COUNT(*)'), 'SUM(widget_order)')->findMany();
$expected = "SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`";
Tester::check_equal("Literal expression in select many result columns", $expected);

ORM::table('widget')->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
$expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Simple join", $expected);

ORM::table('widget')->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findOne(5);
$expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` WHERE `widget`.`id` = '5' LIMIT 1";
Tester::check_equal("Simple join with whereIdIs method", $expected);

ORM::table('widget')->innerJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
$expected = "SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Inner join", $expected);

ORM::table('widget')->leftJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
$expected = "SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Left outer join", $expected);

ORM::table('widget')->rightJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
$expected = "SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Right outer join", $expected);

ORM::table('widget')->fullJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
$expected = "SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Full outer join", $expected);

ORM::table('widget')
    ->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))
    ->join('widget_nozzle', array('widget_nozzle.widget_id', '=', 'widget.id'))
    ->findMany();
$expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` JOIN `widget_nozzle` ON `widget_nozzle`.`widget_id` = `widget`.`id`";
Tester::check_equal("Multiple join sources", $expected);

ORM::table('widget')->alias('w')->findMany();
$expected = "SELECT * FROM `widget` `w`";
Tester::check_equal("Main table alias", $expected);

ORM::table('widget')->join('widget_handle', array('wh.widget_id', '=', 'widget.id'), 'wh')->findMany();
$expected = "SELECT * FROM `widget` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `widget`.`id`";
Tester::check_equal("Join with alias", $expected);

ORM::table('widget')->join('widget_handle', "widget_handle.widget_id = widget.id")->findMany();
$expected = "SELECT * FROM `widget` JOIN `widget_handle` ON widget_handle.widget_id = widget.id";
Tester::check_equal("Join with string constraint", $expected);

ORM::table('widget')->distinct()->select('name')->findMany();
$expected = "SELECT DISTINCT `name` FROM `widget`";
Tester::check_equal("Select with DISTINCT", $expected);

$widget = ORM::table('widget')->create();
$widget->name = "Fred";
$widget->age = 10;
$widget->save();
$expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', '10')";
Tester::check_equal("Insert data", $expected);

$widget = ORM::table('widget')->create();
$widget->name = "Fred";
$widget->age = 10;
$widget->set_expr('added', 'NOW()');
$widget->save();
$expected = "INSERT INTO `widget` (`name`, `age`, `added`) VALUES ('Fred', '10', NOW())";
Tester::check_equal("Insert data containing an expression", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->name = "Fred";
$widget->age = 10;
$widget->save();
$expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '10' WHERE `id` = '1'";
Tester::check_equal("Update data", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->name = "Fred";
$widget->age = 10;
$widget->set_expr('added', 'NOW()');
$widget->save();
$expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '10', `added` = NOW() WHERE `id` = '1'";
Tester::check_equal("Update data containing an expression", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->set(array("name" => "Fred", "age" => 10));
$widget->save();
$expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '10' WHERE `id` = '1'";
Tester::check_equal("Update multiple fields", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->set(array("name" => "Fred", "age" => 10));
$widget->set_expr(array("added" => "NOW()", "lat_long" => "GeomFromText('POINT(1.2347 2.3436)')"));
$widget->save();
$expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '10', `added` = NOW(), `lat_long` = GeomFromText('POINT(1.2347 2.3436)') WHERE `id` = '1'";
Tester::check_equal("Update multiple fields containing an expression", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->set(array("name" => "Fred", "age" => 10));
$widget->set_expr(array("added" => "NOW()", "lat_long" => "GeomFromText('POINT(1.2347 2.3436)')"));
$widget->lat_long = 'unknown';
$widget->save();
$expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '10', `added` = NOW(), `lat_long` = 'unknown' WHERE `id` = '1'";
Tester::check_equal("Update multiple fields containing an expression (override previously set expression with plain value)", $expected);

$widget = ORM::table('widget')->findOne(1);
$widget->delete();
$expected = "DELETE FROM `widget` WHERE `id` = '1'";
Tester::check_equal("Delete data", $expected);

// Regression tests

$widget = ORM::table('widget')->select('widget.*')->findOne();
$expected = "SELECT `widget`.* FROM `widget` LIMIT 1";
Tester::check_equal("Issue #12 - incorrect quoting of column wildcard", $expected);

$widget = ORM::table('widget')->whereRaw('username LIKE "ben%"')->findMany();
$expected = 'SELECT * FROM `widget` WHERE username LIKE "ben%"';
Tester::check_equal('Issue #57 - _logQuery method raises a warning when query contains "%"', $expected);

$widget = ORM::table('widget')->whereRaw('comments LIKE "has been released?%"')->findMany();
$expected = 'SELECT * FROM `widget` WHERE comments LIKE "has been released?%"';
Tester::check_equal('Issue #57 - _logQuery method raises a warning when query contains "?"', $expected);

// Tests that alter ORM's config are done last

ORM::configure('orm.id_column', 'primary_key');
ORM::table('widget')->findOne(5);
$expected = "SELECT * FROM `widget` WHERE `primary_key` = '5' LIMIT 1";
Tester::check_equal("Setting: id_column", $expected);

ORM::configure('orm.id_overrides', array(
    'widget' => 'widget_id',
    'widget_handle' => 'widget_handle_id',
));

ORM::table('widget')->findOne(5);
$expected = "SELECT * FROM `widget` WHERE `widget_id` = '5' LIMIT 1";
Tester::check_equal("Setting: id_column_overrides, first test", $expected);

ORM::table('widget_handle')->findOne(5);
$expected = "SELECT * FROM `widget_handle` WHERE `widget_handle_id` = '5' LIMIT 1";
Tester::check_equal("Setting: id_column_overrides, second test", $expected);

ORM::table('widget_nozzle')->findOne(5);
$expected = "SELECT * FROM `widget_nozzle` WHERE `primary_key` = '5' LIMIT 1";
Tester::check_equal("Setting: id_column_overrides, third test", $expected);

ORM::table('widget')->use_id_column('new_id')->findOne(5);
$expected = "SELECT * FROM `widget` WHERE `new_id` = '5' LIMIT 1";
Tester::check_equal("Instance ID column, first test", $expected);

ORM::table('widget_handle')->use_id_column('new_id')->findOne(5);
$expected = "SELECT * FROM `widget_handle` WHERE `new_id` = '5' LIMIT 1";
Tester::check_equal("Instance ID column, second test", $expected);

ORM::table('widget_nozzle')->use_id_column('new_id')->findOne(5);
$expected = "SELECT * FROM `widget_nozzle` WHERE `new_id` = '5' LIMIT 1";
Tester::check_equal("Instance ID column, third test", $expected);

// Test caching. This is a bit of a hack.
ORM::configure('orm.caching', true);
ORM::table('widget')->where('name', 'Fred')->where('age', 17)->findOne();
ORM::table('widget')->where('name', 'Bob')->where('age', 42)->findOne();
$expected = ORM::get_lastQuery();
ORM::table('widget')->where('name', 'Fred')->where('age', 17)->findOne(); // this shouldn't run a query!
Tester::check_equal("Caching, same query not run twice", $expected);

Tester::report();
