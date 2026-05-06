<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;


class ServiceProxy
{
    protected $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function __call($name, $arguments)
    {
        $result = call_user_func_array([$this->service, $name], $arguments);
        return $this->service->responseFormat($result);
    }
}