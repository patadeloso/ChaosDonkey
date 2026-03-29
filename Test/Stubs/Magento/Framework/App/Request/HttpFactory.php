<?php
declare(strict_types=1);

namespace Magento\Framework\App\Request;

class HttpFactory
{
    public function create(): Http
    {
        return new Http();
    }
}
