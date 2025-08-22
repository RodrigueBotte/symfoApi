<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class BookController extends AbstractController
{
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public function getAllBooks( BookRepository $bookRepository, SerializerInterface $serializer, Request $rq, TagAwareCacheInterface $cachePool): JsonResponse {
        
        // mise en place de de la pagination, si aucune limite imposé, on met 1 page et 3 artcle de base
        $page = $rq->get('page', 1);
        $limit = $rq->get('limit', 3);

        // mise en place d'un id de cache
        $idCache = "getEllBooks-" . $page . "-" . $limit;
        $jsonBookList = $cachePool->get($idCache, function(ItemInterface $item) use ($bookRepository, $page, $limit, $serializer){
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($bookList, 'json', $context);
        });
        
        return new JsonResponse(
            $jsonBookList,
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(int $id, SerializerInterface
    $serializer, BookRepository $bookRepository, VersioningService $versioningService): JsonResponse
    {
        $book = $bookRepository->find($id);
        if ($book) {
            $version = $versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getBooks']);
            $context->setVersion($version);
            $jsonBook = $serializer->serialize($book, 'json', $context);
            return new JsonResponse( $jsonBook, Response::HTTP_OK, [], true
                );
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse {
        
        $cachePool->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droit suffisants pour créer un livre')]
    public function createBook(Request $rq, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer, AuthorRepository $authorRepository, ValidatorInterface $valid): JsonResponse {
        $book = $serializer->deserialize($rq->getContent(), Book::class, 'json');

        $errors = $valid->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        // si nous n'avons d'author associé, lui donne -1 sinon, on lui l'associe a un l'author en question
        $content = $rq->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepository->find($idAuthor));
        
        $em->persist($book);
        $em->flush();
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('detailBook',['id'=>$book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        
        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location"=>$location], true);
    }

    // #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    // function updateBook(Request $rq, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository  ): JsonResponse {
        
    //     $updatedBook = $serializer->deserialize($rq->getContent(), Book::class, 'json', 
    //         [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]
    //     );
        
    //     $content = $rq->toArray();
    //     $idAuthor = $content['idAuthor'] ?? -1;

    //     $updatedBook->setAuthor($authorRepository->find($idAuthor));
    //     $em->persist($updatedBook);
    //     $em->flush();
        
    //     return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    // }

    
}