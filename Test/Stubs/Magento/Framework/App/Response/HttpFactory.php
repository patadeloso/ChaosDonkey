<?php
declare(strict_types=1);

namespace Magento\Framework\App\Response;

class HttpFactory
{
    public function create(): Http
    {
        return new Http();
    }
}
