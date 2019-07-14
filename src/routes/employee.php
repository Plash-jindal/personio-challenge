<?php

use Slim\Http\Request;
use Slim\Http\Response;

$app->add(function (Request $req, Response $res, $next) {
    $headers = $req->getHeaders();
    if(is_null($headers['HTTP_BASIC_AUTH'])) {
        return respond($res)->error("You are not authorized to access this endpoint",403);
    } elseif ($headers['HTTP_BASIC_AUTH'][0] != "personiosecureendpoint") {
        return respond($res)->error("Invalid auth credentials", 403);
    } else
        $res = $next($req, $res);
    return $res;
});

// POST /employees
$app->post('/employee', function (Request $req, Response $res) {
    // Parsing the payload
    $data = $req->getParsedBody();

    // Building and inserting the employee Hierarchy
    createEmployeeHierarchy($data);

    // Generating Response Hierarchy
    $result = getEmployeesHierarchy();

    return respond($res)->ok($result);
});

// GET /employees
$app->get('/employee', function (Request $req, Response $res) {

    // Generating Response Hierarchy
    $result = getEmployeesHierarchy();

    return respond($res)->ok($result);
});

/**
 * @param $input (Employee Payload)
 */
function createEmployeeHierarchy($input)
{
    $db = new SQLite3(__DIR__ . '/../../db/employees.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

    //If the supervisor doesn't exist, create it & return the ID.
    foreach ($input as $key => $value) {

        $sup_id = $db->querySingle('SELECT "id" FROM "employees" WHERE "name" = "' . $value . '"');

        if (is_null($sup_id) == 1) {
            $db->query('INSERT INTO "employees" ("name", "supervisor_id") VALUES ("' . $value . '", null)');
        }
    }

    //If the employee doesn't exists, create it & save with the supervisor ID
    foreach ($input as $key => $value) {
        $sup_id = $db->querySingle('SELECT "id" FROM "employees" WHERE "name" = "' . $value . '"');
        //Insert employee
        $emp_id = $db->querySingle('SELECT "id" FROM "employees" WHERE "name" =  "' . $key . '"');
        if (is_null($emp_id) == 1) {
            $db->query('INSERT INTO "employees" ("name", "supervisor_id") VALUES ("' . $key . '", ' . $sup_id . ')');
        } else {
            $db->query('UPDATE "employees" SET "supervisor_id" = ' . $sup_id . ' WHERE "id" = ' . $emp_id);
        }
    }
    $db->close();
}

/**
 * @return array
 */
function getEmployeesHierarchy()
{
    $db = new SQLite3(__DIR__ . '/../../db/employees.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $res = $db->query('SELECT "name" , "id" FROM "employees" WHERE "supervisor_id" IS NULL');
    $root = $res->fetchArray(SQLITE3_ASSOC);
    $supervisorEmployees = getSupervisorEmployees($root);
    $db->close();
    return array($root["name"] => $supervisorEmployees);
}

/**
 * @param $supervisor
 * @return array|void
 */
function getSupervisorEmployees($supervisor)
{
    $db = new SQLite3(__DIR__ . '/../../db/employees.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $res = $db->query('SELECT "name", "id" FROM "employees" WHERE "supervisor_id" = ' . $supervisor['id']);
    if (is_null($res)) {
        return;
    }
    $subEmployee = [];

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        array_push($subEmployee, $row["name"]);
        $children = getSupervisorEmployees($row);
        array_push($subEmployee, $children);
    }
    $db->close();
    return $subEmployee;
}
