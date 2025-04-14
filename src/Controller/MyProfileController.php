<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MyProfileController extends AbstractController
{
    #[Route('/my-profile', name: 'app_my_profile')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('my_profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/upload-profile-image', name: 'app_upload_profile_image', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function upload(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        $imageFile = $request->files->get('profile_image');

        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

            $imageFile->move($this->getParameter('profile_images_directory'), $newFilename);

            $user->setProfileImage($newFilename);
            $em->flush();
        }

        return $this->redirectToRoute('app_my_profile');
    }

    #[Route('/update-profile', name: 'app_update_profile', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            throw $this->createAccessDeniedException('You have to be logged in to update your profile.');
        }

        $user->setUsername($request->request->get('username'));
        $user->setEmail($request->request->get('email'));

        $newPassword = $request->request->get('password');
        if (!empty($newPassword)) {
            $user->setPassword($hasher->hashPassword($user, $newPassword));
        }

        $em->flush();

        return $this->redirectToRoute('app_my_profile');
    }

    #[Route('/delete-account', name: 'app_delete_account', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteAccount(EntityManagerInterface $em, TokenStorageInterface $tokenStorage, Request $request): Response
    {
        $user = $this->getUser();

        $tokenStorage->setToken(null);
        $em->remove($user);
        $em->flush();

        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_home');
    }
}
