<?php
declare(strict_types=1);

namespace Magento\Framework\App;

use Magento\Framework\App\Request\Http;

interface FrontControllerInterface
{
    public function dispatch(Http $request): void;
}
