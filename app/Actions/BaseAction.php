<?php

namespace App\Actions;

use App\Contracts\Action as ActionContract;
use Exception;

abstract class BaseAction implements ActionContract
{
    /**
     * Handle action
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function execute(...$params): mixed
    {
        if (!method_exists($this, 'handle')) {
            throw new Exception('Method "handle" does not exist in: ' . get_class($this));
        }

        return $this->handle(...$params);
    }
}
