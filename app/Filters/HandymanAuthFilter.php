<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\CustomIonAuth;

class HandymanAuthFilter implements FilterInterface
{
    private $publicHandymanRoutes = [
        '/handyman/login',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $path = $request->getUri()->getPath();

        foreach ($this->publicHandymanRoutes as $publicRoute) {
            if (strpos($path, $publicRoute) === 0) {
                return $request;
            }
        }

        $ionAuth = new CustomIonAuth();

        if (!$ionAuth->loggedIn() || !$ionAuth->isHandyman()) {
            return redirect()->to('/handyman/login');
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
