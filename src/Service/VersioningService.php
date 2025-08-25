<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class VersioningService{
    private $requestStack;
    private string $defaultVersion;

    public function __construct(RequestStack $rq, ParameterBagInterface $params)
    {
        $this->requestStack = $rq;
        $this->defaultVersion = $params->get('default_api_version');
    }

    public function getVersion(): string {
        $version = $this->defaultVersion;

        $request = $this->requestStack->getCurrentRequest();
        $accept = $request?->headers->get('Accept');

        $entete = explode(';', $accept);

        foreach ($entete as $value) {
            if (strpos($value, 'version') !== false) {
                $version = explode('=', $value);
                $version = $version[1];
                break;
            }
        }
        return $version;
    }
}

?>