<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Adding middleware for authentication
$app->add(function (Request $req, Response $res, $next) {
    $headers = $req->getHeaders();
    if (is_null($headers['HTTP_BASIC_AUTH'])) {
        return respond($res)->error("You are not authorized to access this endpoint", 403);
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

    try {
        // Building and inserting the employee Hierarchy
        createEmployeeHierarchy($data);

        // Generating Response Hierarchy
        $result = getEmployeesHierarchy();
    } catch (Exception $e) {
        return respond($res)->error($e->getMessage());
    }
    return respond($res)->ok($result);
});

// GET Hierarchy of all employees
$app->get('/employee', function (Request $req, Response $res) {

    // Generating Response Hierarchy
    $result = getEmployeesHierarchy();

    return respond($res)->ok($result);
});

// GET Hierarchy of a given employee
$app->get('/employee/{name}', function (Request $req, Response $res, array $args) {

    $employeeName = $args['name'];

    // Generating Response Hierarchy
    $result = getEmployeesHierarchy($employeeName);

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
 * @param null $employeeName
 * @return array
 */
function getEmployeesHierarchy($employeeName = null)
{
    $db = new SQLite3(__DIR__ . '/../../db/employees.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    if ($employeeName == null) {

        $query = $db->query('SELECT "name" , "id" FROM "employees" WHERE "supervisor_id" IS NULL');
        $employeeCount = $db->querySingle('SELECT COUNT("id") FROM "employees"');
        // There are no employees registered
        if ($employeeCount == 0) {
            $db->close();
            throw new Exception("There are no employees present in the company");
        } else {
            $supervisorsCount = $db->querySingle('SELECT COUNT("id") FROM "employees" WHERE "supervisor_id" IS NULL');
            // If there are multiple top level supervisors, throw an error
            if($supervisorsCount > 1) {
                // Flushing out the wrong inserted data (Can be done using transactions & rollback on errors but not using PDO)
                $db->query('DELETE FROM "employees"');
                $db->close();
                throw new Exception("There are multiple roots present, please check your hierarchy for submission");
            }

            // If there are no supervisors present and looping issue is there in the payload data
            if ($supervisorsCount == 0) {
                // Flushing out the wrong inserted data (Can be done using transactions & rollback on errors but not using PDO)
                $db->query('DELETE FROM "employees"');
                $db->close();
                throw new Exception("There is looping issue with the supervisors payload, please check your hierarchy for submission");
            }
        }
        $root = $query->fetchArray(SQLITE3_ASSOC);
    } else {
        $query = $db->query('SELECT "id", "name" FROM "employees" WHERE "name" =  "' . $employeeName . '"');
        $root = $query->fetchArray(SQLITE3_ASSOC);
        if (empty($root)) {
            $db->close();
            throw new Exception("There are no employees present in the company with the name:" .$employeeName);
        }
    }

    // Fetching the employees
    $supervisorEmployees = getSupervisorEmployees($root);
    $employeeHierarchy = array($root["name"] => $supervisorEmployees);
    $db->close();
    return $employeeHierarchy;
}

/**
 * @param $supervisor
 * @return array|void
 */
function getSupervisorEmployees($supervisor)
{
    $db = new SQLite3(__DIR__ . '/../../db/employees.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $res = $db->query('SELECT "name", "id" FROM "employees" WHERE "supervisor_id" = ' . $supervisor['id']);
    if (empty($res)) {
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
