<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Messages (roadmap step 9c), on a conversation:
 *
 * - MESSAGE_VIEW: its participants, and the members of the group it was written to (an
 *   administrator does not read the conversations of others);
 * - MESSAGE_REPLY: the same, while they may write messages (MESSAGE_SEND).
 *
 * @extends Voter<string, Conversation>
 */
final class ConversationVoter extends Voter
{
    public const string VIEW = 'MESSAGE_VIEW';
    public const string REPLY = 'MESSAGE_REPLY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::REPLY], true) && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $group = $subject->getGroup();
        $reads = $subject->isParticipant($user) || (null !== $group && $user->isInGroup($group->getCode()));

        return self::VIEW === $attribute ? $reads : $reads && $user->hasPermission(Permission::MessageSend);
    }
}
