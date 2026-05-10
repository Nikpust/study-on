<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\BillingUnavailableException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\BillingCourseFormHandler;
use App\Service\CoursePaymentInfoProvider;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses')]
final class CourseController extends AbstractController
{
    public function __construct(
        private readonly CoursePaymentInfoProvider $paymentInfoProvider,
        private readonly BillingClient $billingClient,
        private readonly BillingCourseFormHandler $billingCourseFormHandler,
    ) {
    }

    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        $courses = [];
        $error = null;

        try {
            $courses = $this->paymentInfoProvider->getCoursesWithPaymentInfo(
                $courseRepository->findAll(),
                $this->getUser()
            );
        } catch (BillingUnavailableException $e) {
            $error = $e->getMessage();
        }
        return $this->render('course/index.html.twig', [
            'courses' => $courses,
            'error' => $error,
        ]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->billingCourseFormHandler->validateBillingCourseData($form);

            if (!$form->isValid()) {
                return $this->renderFormWithErrors('course/new.html.twig', $course, $form);
            }

            try {
                $response = $this->billingClient->createCourse(
                    $this->getUser()->getApiToken(),
                    $this->billingCourseFormHandler->getCourseData($form)
                );
            } catch (BillingUnavailableException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->renderFormWithErrors('course/new.html.twig', $course, $form);
            }

            if (($response['_status_code'] ?? 500) !== Response::HTTP_CREATED) {
                $this->billingCourseFormHandler->addBillingErrorsToForm($form, $response);
                return $this->renderFormWithErrors('course/new.html.twig', $course, $form);
            }

            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Course $course): Response
    {
        try {
            $courseInfo = $this->paymentInfoProvider->getCoursePaymentInfo(
                $course,
                $this->getUser()
            );
        } catch (BillingUnavailableException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('course/show.html.twig', [
            'courseInfo' => $courseInfo,
        ]);
    }

    #[Route('/{id}/pay', name: 'app_course_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function pay(Course $course): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $data = $this->billingClient->payCourse($user->getApiToken(), (string) $course->getCode());

            if (($data['_status_code'] ?? 500) === 200) {
                $this->addFlash(
                    'success',
                    'Курс успешно оплачен!'
                );
            } else {
                $this->addFlash(
                    'danger',
                    $data['message'] ?? 'Оплата временно недоступна. Попробуйте позднее.'
                );
            }
        } catch (BillingUnavailableException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_course_show', [
            'id' => $course->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $currentCode = $course->getCode();

        try {
            $courseInfo = $this->billingClient->getCourse($currentCode);
        } catch (BillingUnavailableException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        $options = [
            'billing_type' => $courseInfo['type'] ?? 'free',
            'billing_price' => $courseInfo['price'] ?? null,
        ];

        $form = $this->createForm(CourseType::class, $course, $options);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->billingCourseFormHandler->validateBillingCourseData($form);

            if (!$form->isValid()) {
                return $this->renderFormWithErrors('course/edit.html.twig', $course, $form);
            }

            try {
                $response = $this->billingClient->editCourse(
                    $this->getUser()->getApiToken(),
                    $currentCode,
                    $this->billingCourseFormHandler->getCourseData($form)
                );
            } catch (BillingUnavailableException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->renderFormWithErrors('course/edit.html.twig', $course, $form);
            }

            if (($response['_status_code'] ?? 500) !== Response::HTTP_OK) {
                $this->billingCourseFormHandler->addBillingErrorsToForm($form, $response);
                return $this->renderFormWithErrors('course/edit.html.twig', $course, $form);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_course_show', [
                'id' => $course->getId()
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    private function renderFormWithErrors(string $template, Course $course, FormInterface $form): Response
    {
        return $this->render($template, [
            'course' => $course,
            'form' => $form,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
