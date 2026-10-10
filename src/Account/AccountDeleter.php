<?php

declare(strict_types=1);

namespace App\Account;

use App\Entity\ContactRequest;
use App\Entity\ConversationParticipant;
use App\Entity\FlightComment;
use App\Entity\Membership;
use App\Entity\User;
use App\Flight\FlightImporter;
use App\Repository\FlightRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes an account at its owner's request (GDPR, "Confidentialité et données"):
 *
 * - its comments, messages to the club and messages to other members are deleted, and so are the
 *   conversations left without any message;
 * - its flights are deleted with their files, or kept anonymised (the owner's choice): the analysis
 *   stays for the sessions and records, without name, competition id, account or file;
 * - its memberships are kept for the books (ten years), with the name, season, amounts and dates
 *   only;
 * - then the account itself (posts it wrote stay, without author).
 *
 * The last account holding every permission cannot delete itself: the site would have no
 * administrator left.
 */
final readonly class AccountDeleter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FlightRepository $flights,
        private FlightImporter $importer,
        private UserRepository $users,
    ) {
    }

    public function canDelete(User $user): bool
    {
        if (!$user->hasAllPermissions()) {
            return true;
        }
        foreach ($this->users->findAll() as $other) {
            if ($other !== $user && $other->hasAllPermissions()) {
                return true;
            }
        }

        return false;
    }

    public function delete(User $user, bool $keepFlights): void
    {
        if (!$this->canDelete($user)) {
            throw new \LogicException('The last administrator cannot delete their account.');
        }
        foreach ($this->entityManager->getRepository(FlightComment::class)->findBy(['author' => $user]) as $comment) {
            $this->entityManager->remove($comment);
        }
        foreach ($this->entityManager->getRepository(ContactRequest::class)->findBy(['user' => $user]) as $request) {
            $this->entityManager->remove($request);
        }
        foreach ($this->entityManager->getRepository(ConversationParticipant::class)->findBy(['user' => $user]) as $participant) {
            $conversation = $participant->getConversation();
            foreach ($conversation->getMessages()->toArray() as $message) {
                if ($message->getAuthor() === $user) {
                    $conversation->getMessages()->removeElement($message);
                }
            }
            $conversation->getParticipants()->removeElement($participant);
            if ($conversation->getMessages()->isEmpty()) {
                $this->entityManager->remove($conversation);
            }
        }
        foreach ($this->flights->findBy(['owner' => $user]) as $flight) {
            $this->importer->removeFile($flight);
            if ($keepFlights) {
                $flight->anonymize();
            } else {
                $this->entityManager->remove($flight);
            }
        }
        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['user' => $user]) as $membership) {
            $membership->anonymize();
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
