<?php
declare(strict_types=1);

namespace Magento\Framework\App;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as HttpResponse;

interface FrontControllerInterface
{
    public function dispatch(Http $request): HttpResponse;
}
