<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class AuthorController extends AbstractController
{
    #[Route('api/author', name: 'author', methods: 'GET')]
    public function getAllAuthor(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse
    {
        $authorList = $authorRepository->findAll();
        $jsonAuthorList = $serializer->serialize($authorList, 'json', ['groups'=>'getBooks']);
        return new JsonResponse(
            $jsonAuthorList, Response::HTTP_OK, [], true
        );
    }

    #[Route('api/author/{id}', name: 'detailAuthor', methods: 'GET')]
    public function getDetailBook(int $id, SerializerInterface $serializer, AuthorRepository $authorRepository) : JsonResponse {
        $author = $authorRepository->find($id);
        if ($author) {
            $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getBooks']);
            return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(
            null, Response::HTTP_NOT_FOUND
        );
    }
}