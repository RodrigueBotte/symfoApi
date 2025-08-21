<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AuthorController extends AbstractController
{
    #[Route('/api/author', name: 'author', methods: ['GET'])]
    public function getAllAuthor(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $rq, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $rq->get('page', 1);
        $limit = $rq->get('limit', 4);

        $idCache = "getAllAuthor-" . $page . "-" . $limit;
        $jsonAuthorList = $cachePool->get($idCache, function(ItemInterface $item) use ($authorRepository, $page, $limit, $serializer){
            $item->tag("authorCache");
            $bookList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($bookList, 'json', ['groups'=> 'getBooks']);
        });

        return new JsonResponse(
            $jsonAuthorList, Response::HTTP_OK, [], true
        );
    }

    #[Route('/api/author/{id}', name: 'detailAuthor', methods: ['GET'])]
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

    #[Route('/api/author/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em) : JsonResponse {
        foreach ($author->getBooks() as $book) {
            $em->remove($book);
        }
        $em->remove($author);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/author', name: 'createAuthor', methods: ['POST'])]
    public function createAuthor(Request $rq, EntityManagerInterface $em, UrlGeneratorInterface $url, SerializerInterface $serializer, ValidatorInterface $valid) : JsonResponse {
        $author = $serializer->deserialize($rq->getContent(), Author::class, 'json');

        $errors = $valid->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        $em->persist($author);
        $em->flush();
        
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups'=>'getBooks']);

        // On crée le chemin afin de pouvoir aller voir les détail de l'auteur en fonction de son id associé
        $location = $url->generate('detailAuthor', ['id'=>$author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location"=>$location], true);
    }

    #[Route('/api/author/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(Request $rq, SerializerInterface $serializer, Author $currentAuthor, EntityManagerInterface $em, ValidatorInterface $valid): JsonResponse {
        $serializer->deserialize($rq->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE=>$currentAuthor]);

        $errors = $valid->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}