<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;
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
            try {
                $paidUserCourses = $this->billingClient->getTransactions($user->getApiToken(), [
                    'type' => 'payment',
                    'skip_expired' => true,
                ]);

                $paidUserCourses = $this->indexByCode($paidUserCourses, 'course_code');
            } catch (BillingUnavailableException $e) {
                throw new BillingUnavailableException($e->getMessage());
            }
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
