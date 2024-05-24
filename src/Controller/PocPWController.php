<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PocPWController extends AbstractController
{
    private Request $request;
    private $connection;

    public function __construct(RequestStack $request,EntityManagerInterface $entityManager)
    {
        $this->request = $request->getMainRequest();
        $this->connection = $entityManager->getConnection();;
    }

    #[Route('/query', name: 'query')]
    public function query(): Response
    {
        $result = $this->connection->fetchAssociative('Select * from muster WHERE id = "'.$this->request->get('id').'"');

    return $this->json($result);
    }


}