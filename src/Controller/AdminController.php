<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use App\Repository\CategoryRepository;
use App\Entity\User;
use App\Entity\Category;
use App\Form\UserType;
use App\Form\CategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\File\File;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    // User Management

    #[Route('/admin/users', name: 'admin_user_index')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function userIndex(UserRepository $userRepository, Request $request): Response
    {
        $search = $request->query->get('search', ''); 

        $users = $userRepository->findBySearch($search);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    #[Route("/admin/user/{id}/edit", name: 'admin_user_edit')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function userEdit(User $user, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Zmienna pomocnicza do przechowania starej nazwy pliku
        $originalProfileImage = $user->getProfileImage();

        // Jeśli plik istnieje, przypisz obiekt File
        if ($originalProfileImage) {
            $filePath = $this->getParameter('profile_images_directory') . '/' . $originalProfileImage;
            if (file_exists($filePath)) {
                $user->setProfileImage(new File($filePath));
            } else {
                // Plik nie istnieje – ustaw na null, aby uniknąć błędu typu
                $user->setProfileImage(null);
            }
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('profileImage')->getData();

            if ($uploadedFile) {
                // Usuń stary plik jeśli istnieje
                if ($originalProfileImage) {
                    $oldFilePath = $this->getParameter('profile_images_directory') . '/' . $originalProfileImage;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $newFilename = uniqid().'.'.$uploadedFile->guessExtension();
                $uploadedFile->move(
                    $this->getParameter('profile_images_directory'),
                    $newFilename
                );
                $user->setProfileImage($newFilename);
            } else {
                // Jeśli nie przesłano nowego pliku, zachowaj starą nazwę pliku
                $user->setProfileImage($originalProfileImage);
            }

            // Obsłuż hasło – tylko jeśli zostało wpisane
            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Obsłuż zmianę roli
            $selectedRole = $form->get('roles')->getData();
            $user->setRoles([$selectedRole]);

            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route("/admin/user/{id}/delete", name: "admin_user_delete", methods: ["POST"])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function userDelete(User $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('admin_user_index');
    }

    // Category Management

    #[Route('/admin/categories', name: 'admin_category_index')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function categoryIndex(CategoryRepository $categoryRepository, Request $request): Response
    {
        $search = $request->query->get('search', ''); 

        $categories = $categoryRepository->findBySearch($search);

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    #[Route("/admin/category/new", name: 'admin_category_create')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function categoryCreate(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category created successfully.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route("/admin/category/{id}/edit", name: 'admin_category_edit')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function categoryEdit(Category $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Category updated successfully.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    #[Route("/admin/category/{id}/delete", name: "admin_category_delete", methods: ["POST"])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function categoryDelete(Category $category, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('admin_category_index');
    }
}
