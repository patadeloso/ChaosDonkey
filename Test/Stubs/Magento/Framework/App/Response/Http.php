<?php
declare(strict_types=1);

namespace Magento\Framework\App\Response;

class Http
{
    public function getHttpResponseCode(): int
    {
        return 200;
    }

    public function getBody(): string
    {
        return '{}';
    }
}
