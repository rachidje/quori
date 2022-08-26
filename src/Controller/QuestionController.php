<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Question;
use App\Entity\Vote;
use App\Form\CommentType;
use App\Form\QuestionType;
use App\Repository\QuestionRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuestionController extends AbstractController
{
    
    #[Route('/question/ask', name: 'question_form')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function ask(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $question = new Question();

        $formQuestion = $this->createForm(QuestionType::class, $question);
        $formQuestion->handleRequest($request);

        if($formQuestion->isSubmitted() && $formQuestion->isValid()) {
            $question->setNbResponse(0)
                    ->setRating(0)
                    ->setAuthor($user)
                    ->setCreatedAt(new \DateTimeImmutable());

            $em->persist($question);
            $em->flush();
            $this->addFlash('success', 'Votre question a été ajoutée.');

            return $this->redirectToRoute('home');
        }

        return $this->render('question/index.html.twig', [
            'form' => $formQuestion->createView(),
        ]);
    }

    #[Route('/question/search/{search}', name: 'question_search', priority: 1)]
    public function questionSearch(QuestionRepository $questionRepo, string $search = "none") {
        
        if($search === "none") {
            $questions = [];
        } else {
            $questions = $questionRepo->findBySearch($search);
        }

        return $this->json(json_encode($questions));
    }

    #[Route('/question/{id}', name: 'question_show')]
    public function show(Request $request, QuestionRepository $questionRepo, int $id, EntityManagerInterface $em) : Response {

        $question = $questionRepo->findWithCommentsAuthors($id);

        $options = [
            'question' => $question
        ];

        $user = $this->getUser();

        if($user) {
            $comment = new Comment();
            $commentForm = $this->createForm(CommentType::class, $comment);
            $commentForm->handleRequest($request);

            if($commentForm->isSubmitted() && $commentForm->isValid()) {
                $comment->setCreatedAt(new \DateTimeImmutable())
                        ->setRating(0)
                        ->setAuthor($user)
                        ->setQuestion($question);

                $question->setNbResponse($question->getNbResponse() + 1);

                $em->persist($comment);
                $em->flush();

                $this->addFlash('success', 'Votre réponse a été publiée.');
                return $this->redirect($request->getUri());
            }
            $options['form'] = $commentForm->createView();
        }

        return $this->render('question/show.html.twig', $options);
    }

    #[Route('/question/rating/{id}/{score}', name: 'question_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function questionRating(Question $question, int $score, EntityManagerInterface $em, Request $request, VoteRepository $voteRepo)
    {
        $user = $this->getUser();

        if($user !== $question->getAuthor()) {
            $vote = $voteRepo->findOneBy([
                'author' => $user,
                'question' => $question
            ]);

            if($vote) {
                if(($vote->getIsLiked() && $score > 0) || (!$vote->getIsLiked() && $score < 0)){
                    $em->remove($vote);
                    $question->setRating($question->getRating() + ($score > 0 ? -1 : 1));
                } else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $question->setRating($question->getRating() + ($score > 0 ? 2 : -2));
                }
            } else {
                $vote = new Vote();
                $vote->setAuthor($user)
                    ->setQuestion($question)
                    ->setIsLiked($score > 0 ? true : false);

                $em->persist($vote);
                $question->setRating($question->getRating() + $score);
            }
    
        }
        
        $em->flush();

        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }

    #[Route('/comment/rating/{id}/{score}', name: 'comment_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function commentRating(Comment $comment, int $score, EntityManagerInterface $em, Request $request, VoteRepository $voteRepo)
    {
        $user = $this->getUser();

        if($user !== $comment->getAuthor()) {
            $vote = $voteRepo->findOneBy([
                'author' => $user,
                'comment' => $comment
            ]);

            if($vote) {
                if(($vote->getIsLiked() && $score > 0) || (!$vote->getIsLiked() && $score < 0)){
                    $em->remove($vote);
                    $comment->setRating($comment->getRating() + ($score > 0 ? -1 : 1));
                } else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $comment->setRating($comment->getRating() + ($score > 0 ? 2 : -2));
                }
            } else {
                $vote = new Vote();
                $vote->setAuthor($user)
                    ->setComment($comment)
                    ->setIsLiked($score > 0 ? true : false);

                $em->persist($vote);
                $comment->setRating($comment->getRating() + $score);
            }

        }

        $em->flush();


        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
