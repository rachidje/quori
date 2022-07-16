<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Service\UploaderPicture;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{

    #[Route('/user', name: 'current_user')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function currentUserProfile(UploaderPicture $uploaderPicture, Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        /**
         * @var User
         */
        $user = $this->getUser();
        $userForm = $this->createForm(UserType::class, $user, ['new_user' => false]);
        $userForm->remove('password');
        $userForm->add('newPassword', PasswordType::class, ['label' => 'Nouveau mot de passe', 'required' => false]);
        $userForm->handleRequest($request);

        if($userForm->isSubmitted() && $userForm->isValid()){
            $newPassword = $user->getNewPassword();
            if($newPassword) {
                $hash = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hash);
            }

            $picture = $userForm->get('pictureFile')->getData();
            if($picture) {
                $user->setPicture($uploaderPicture->uploadProfileImage($picture, $user->getPicture()));
            }

            $em->flush();
            $this->addFlash('success', 'Modifications enregistrees');
        }

        return $this->render('user/index.html.twig', [
            'form' => $userForm->createView(),
        ]);
    }

    #[Route('/user/questions', name: 'show_questions')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function showQuestions() : Response {

        return $this->render('user/show_questions.html.twig');
    }

    #[Route('/user/comments', name: 'show_comments')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function showComments() : Response {

        return $this->render('user/show_comments.html.twig');
    }

    #[Route('/user/{id}', name: 'user')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function userProfile(User $user): Response
    {
        $currentUser = $this->getUser();

        if($currentUser === $user){
            return $this->redirectToRoute('current_user');
        }

        return $this->render('user/show.html.twig', ['user' => $user]);
    }
}
