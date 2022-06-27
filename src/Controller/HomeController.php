<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $questions = [
            [
                'title' => 'Je suis une question',
                'content' => "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fugit blanditiis quas sequi cumque? At vel mollitia facilis ipsam suscipit eaque a, accusamus temporibus maiores aut harum qui maxime nostrum minima?",
                'rating' => -12,
                'author' => [
                    'name' => "Rachid Jeffali",
                    'avatar' => "https://randomuser.me/api/portraits/lego/3.jpg"
                ],
                'nbResponse' => 15
            ],
            [
                'title' => 'Je suis une question',
                'content' => "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fugit blanditiis quas sequi cumque? At vel mollitia facilis ipsam suscipit eaque a, accusamus temporibus maiores aut harum qui maxime nostrum minima?",
                'rating' => 15,
                'author' => [
                    'name' => "Julie Desflandres",
                    'avatar' => "https://randomuser.me/api/portraits/women/24.jpg"
                ],
                'nbResponse' => 12
            ]
        ];

        return $this->render('home/index.html.twig', [
            'questions' => $questions
        ]);
    }
}
