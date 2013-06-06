<?php

namespace Issei\MthBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction()
    {
        $cachePath = $this->container->getParameter('issei_mth.current_url_cache_save_path');
        if (!is_file($cachePath) || !($data = include $cachePath)) {
            throw $this->createNotFoundException();
        }

        return array(
            'keyword' => $data['keyword'],
            'url' => $data['url'],
        );
    }
}
