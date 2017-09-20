<?php

namespace Tests;

use ORM;
use Model;
use DummyPDO;
use Tester;

/*
 * Testing for Paris for features specifics to PHP >= 5.3
 *
 * We deliberately don't test the query API - that's ORM's job.
 * We just test Paris-specific functionality.
 *
 * Checks that the generated SQL is correct
 *
 */

require_once dirname(__FILE__) . "/../ORM.php";
require_once dirname(__FILE__) . "/../ORMWrapper.php";
require_once dirname(__FILE__) . "/../Model.php";
require_once dirname(__FILE__) . "/Classes.php";

// Enable logging
ORM::configure('orm.logging', true);

// Set up the dummy database connection
$db = new DummyPDO('sqlite::memory:');
ORM::setDb($db);

class Simple extends Model
{
}

Model::factory('Tests\Simple')->findMany();
$expected = 'SELECT * FROM `tests_simple`';
Tester::check_equal("Namespaced auto table name", $expected);
