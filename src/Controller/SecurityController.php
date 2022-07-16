<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security\UserAuthenticator;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SecurityController extends AbstractController
{

    public function __construct(private $formLoginAuthenticator)
    {
        
    }

    #[Route('/signup', name: 'signup')]
    public function signup(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, UserAuthenticatorInterface $userAuthenticator, MailerInterface $mailer): Response
    {
        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if($userForm->isSubmitted() && $userForm->isValid()){
            $hash = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hash);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Bienvenue sur Quora');

            // Envoi de l'email de bienvenue
            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                    ->subject('Bienvenue a Quora')
                    ->htmlTemplate('@email_templates/welcome.html.twig')
                    ->context([
                        'username' => $user->getFirstname()
                    ]);
            $mailer->send($email);

            return $userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);
        }

        return $this->render('security/signup.html.twig', ['form' => $userForm->createView()]);
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if($this->getUser()) {
            return $this->redirectToRoute('home');
        }
        
        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout()
    {
        
    }

    #[Route('/reset-password-request', name: 'reset_password_request')]
    public function resetPasswordRequest(Request $request, UserRepository $userRepo, ResetPasswordRepository $resetPasswordRepo, EntityManagerInterface $em, MailerInterface $mailer, RateLimiterFactory $passwordRecoveryLimiter) {

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());

        $emailForm = $this->createFormBuilder()
                            ->add('email', EmailType::class, [
                                'constraints' => [
                                    new NotBlank([
                                        'message' => 'Veuillez renseigner ce champ.'
                                    ]),
                                    new Email([
                                        'message' => 'Veuillez entrer un email valide.'
                                    ])
                                ]
                            ])
                            ->getForm();

        $emailForm->handleRequest($request);
        if($emailForm->isSubmitted() && $emailForm->isValid()) {
            if(false === !$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Vous devez attendre 1 heure pour refaire une demande.');
                return $this->redirectToRoute('login');
            }

            $email = $emailForm->get('email')->getData();
            $user = $userRepo->findOneBy(['email' => $email]);
            
            if($user) {

                $oldResetPassword = $resetPasswordRepo->findOneBy(['user' => $user]);
                if($oldResetPassword) {
                    $resetPasswordRepo->remove($oldResetPassword, true);
                }

                $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(40))), 0, 20);
                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user)
                                ->setExpiredAt(new \DateTimeImmutable('+2 hours'))
                                ->setToken(sha1($token));

                $em->persist($resetPassword);
                $em->flush();

                // envoi de l'email
                $resetEmail = new TemplatedEmail();
                $resetEmail->to($email)
                            ->subject('Demande de reinitialisation de mot de passe')
                            ->htmlTemplate('@email_templates/reset_password_request.html.twig')
                            ->context([
                                'username' => $user->getFirstname(),
                                'token' => $token
                            ]);
                $mailer->send($resetEmail);

            }

            $this->addFlash('success', 'Un email vous a ete envoye');
            return $this->redirectToRoute('home');
        }

        return $this->render('security/reset_password_request.html.twig', ['form' => $emailForm->createView()]);
    }

    #[Route('/reset-password/{token}', name: 'reset_password')]
    public function resetPassword(string $token, ResetPasswordRepository $resetPasswordRepo, Request $request, UserPasswordHasherInterface $passwordHasher, RateLimiterFactory $passwordRecoveryLimiter) {

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());

        if(false === !$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous devez attendre 1 heure pour refaire une demande.');
            return $this->redirectToRoute('login');
        }

        $resetPassword = $resetPasswordRepo->findOneBy(['token' => sha1($token)]);
        // si la date a expirer, on le supprime
        if(!$resetPassword || $resetPassword->getExpiredAt() < new DateTime('now')) {
            if($resetPassword){
                $resetPasswordRepo->remove($resetPassword, true);
            }

            $this->addFlash('error', 'Votre demande a expire, veuillez la refaire.');
            return $this->redirectToRoute('login');
        }

        $passwordResetForm = $this->createFormBuilder()
                                ->add('password', PasswordType::class, [
                                    'label' => 'Nouveau mot de passe',
                                    'constraints' => [
                                        new Length([
                                            'min' => 6,
                                            'minMessage' => 'Le mot de passe doit faire au minimum 6 caracteres.'
                                        ]),
                                        new NotBlank([
                                            'message' => 'Veuillez renseigner ce champ.'
                                        ])
                                    ]
                                ])
                                ->getForm();

        $passwordResetForm->handleRequest($request);

        if($passwordResetForm->isSubmitted() && $passwordResetForm->isValid()) {
            $newPassword = $passwordResetForm->get('password')->getData();
            $user = $resetPassword->getUser();

            $hashPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashPassword);

            $resetPasswordRepo->remove($resetPassword, true);

            $this->addFlash('success', 'Votre mot de passe a ete reinitialise.');
            return $this->redirectToRoute('login');
        }

        return $this->render('security/reset_password_form.html.twig', ['form' => $passwordResetForm->createView()]);
    }
}
