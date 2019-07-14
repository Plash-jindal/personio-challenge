<?php

use Slim\Http\Response;

/**
 * A wrapper class to standard the response data.
 */
class Respond extends Response
{
    private $res;

    /**
     * Respond constructor.
     * @param Response $res
     */
    function __construct(Response $res)
    {
        $this->res = $res;
    }

    /**
     * @param null $data
     * @return mixed
     */
    function ok($data = null)
    {
        return $this->res->withJson($data);
    }

    /**
     * @param string $message
     * @param int $status
     * @return mixed
     */
    function error($message = '', $status = 500)
    {
        return $this->res->withJson([
            'success' => false,
            'message' => $message
        ])->withStatus($status);
    }
}

if (!function_exists('respond')) {
    function respond($res)
    {
        return new Respond($res);
    }
}
