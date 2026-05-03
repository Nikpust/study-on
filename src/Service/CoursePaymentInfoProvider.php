<?php

namespace App\Service;

use App\Entity\Course;
use App\Security\User;

final readonly class CoursePaymentInfoProvider
{
    public function __construct(
        private BillingClient $billingClient,
    ) {
    }

    public function getCoursesWithPaymentInfo(array $courses, ?User $user): array
    {
        $billingCourses = $this->indexByCode($this->billingClient->getCourses());

        $paidUserCourses = [];
        if ($user instanceof User) {
            $paidUserCourses = $this->billingClient->getTransactions($user->getApiToken(), [
                'type' => 'payment',
                'skip_expired' => true,
            ]);

            $paidUserCourses = $this->indexByCode($paidUserCourses, 'course_code');
        }

        $result = [];
        foreach ($courses as $course) {
            $code = $course->getCode();

            $billingCourse = $billingCourses[$code] ?? [
                'code' => $code,
                'type' => 'free',
                'price' => null,
            ];

            $payment = $paidUserCourses[$code] ?? null;

            $result[] = [
                'course' => $course,
                'type' => $billingCourse['type'],
                'price' => $billingCourse['price'] ?? null,
                'payment' => $payment,
            ];
        }

        return $result;
    }

    public function getCoursePaymentInfo(Course $course, ?User $user): array
    {
        $code = $course->getCode();

        $billingCourse = $this->billingClient->getCourse($code);

        if (($billingCourse['_status_code'] ?? 500) !== 200) {
            $billingCourse = [
                'code' => $code,
                'type' => 'free',
                'price' => null,
            ];
        }

        $payment = null;
        $canPay = false;

        if ($user instanceof User) {
            $transactions = $this->billingClient->getTransactions($user->getApiToken(), [
                'type' => 'payment',
                'course_code' => $code,
                'skip_expired' => true,
            ]);

            $payment = $transactions[0] ?? null;
            if ($payment === null) {
                $currentUser = $this->billingClient->getCurrentUser($user->getApiToken());

                if (in_array($billingCourse['type'], ['buy', 'rent'], true)) {
                    $balance = (float) ($currentUser['balance'] ?? 0);
                    $price = (float) ($billingCourse['price'] ?? 0);

                    $canPay = $balance >= $price;
                }
            }
        }

        return [
            'course' => $course,
            'type' => $billingCourse['type'],
            'price' => $billingCourse['price'] ?? null,
            'payment' => $payment,
            'can_pay' => $canPay,
        ];
    }

    public function isCourseAvailable(Course $course, ?User $user): bool
    {
        $courseInfo = $this->getCoursePaymentInfo($course, $user);

        if ($courseInfo['type'] === 'free') {
            return true;
        }

        return $courseInfo['payment'] !== null;
    }

    private function indexByCode(array $items, string $key = 'code'): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!isset($item[$key])) {
                continue;
            }

            $result[$item[$key]] = $item;
        }

        return $result;
    }
}
