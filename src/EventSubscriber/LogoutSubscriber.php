<?php

namespace App\EventSubscriber;

use App\Security\User;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $cacheKey = 'billing_token_' . hash('sha256', $user->getEmail());
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $this->cache->deleteItem($cacheKey);
        }
    }
}
