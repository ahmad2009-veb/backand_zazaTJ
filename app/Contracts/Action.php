<?php

namespace App\Contracts;

interface Action
{
    /**
     * Execute action
     * @param array $params
     */
    public function execute(...$params);
}
