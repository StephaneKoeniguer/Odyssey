<?php

namespace App\Event;

use App\Entity\Course;
use App\Entity\User;
use App\Service\MailerService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EasyAdminEvent implements EventSubscriberInterface
{
    public const ROLE_MAPPING = [
        'admin' => 'ROLE_ADMIN',
        'formateur' => 'ROLE_TEACHER',
    ];

    private MailerService $mailerService;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(MailerService $mailerService, UserPasswordHasherInterface $passwordHasher)
    {
        $this->mailerService = $mailerService;
        $this->passwordHasher = $passwordHasher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => ['updateEntity'],
            AfterEntityDeletedEvent::class => ['deleteEntity'],
            BeforeEntityPersistedEvent::class => ['hashPassword'],
        ];
    }

    public function updateEntity(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Course) {

            $user = $entity->getCreatedBy();

            // send mail to inform teacher about validation courses
            $this->mailerService->sendValidationCourse($entity, $user);
        }
    }

    public function deleteEntity(AfterEntityDeletedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Course) {

            $user = $entity->getCreatedBy();

            // send mail to inform teacher about deleted courses
            $this->mailerService->sendDeleteCourse($entity, $user);
        }
    }

    public function hashPassword(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof User) {

            // attribute Symfony roles
            $role = $entity->getRoleUser()->getTypeRole();

            if (isset(EasyAdminEvent::ROLE_MAPPING[$role])) {

                $entity->setRoles([EasyAdminEvent::ROLE_MAPPING[$role]]);
            }

            // Hash password
            $password = $this->passwordHasher->hashPassword($entity, $entity->getPassword());
            $entity->setPassword($password);

            // send mail to inform user created account
            $this->mailerService->createAccount($entity);

        }
    }

}
