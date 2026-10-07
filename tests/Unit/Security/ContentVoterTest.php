<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Group;
use App\Entity\User;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use App\Security\Voter\ContentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ContentVoterTest extends TestCase
{
    private Group $members;
    private Group $committee;

    protected function setUp(): void
    {
        $this->members = new Group('member', 'Membre');
        $this->committee = new Group('committee', 'Comité');
    }

    public function testPublicContentIsVisibleToEveryone(): void
    {
        $content = $this->content(Visibility::Public);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(new NullToken(), $content));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor(), $content));
    }

    public function testAuthenticatedContentNeedsALogin(): void
    {
        $content = $this->content(Visibility::Authenticated);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new NullToken(), $content));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor(), $content));
    }

    public function testGroupContentIsVisibleToMembersOfAnAllowedGroupOnly(): void
    {
        $content = $this->content(Visibility::Groups, $this->committee);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new NullToken(), $content));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->tokenFor(), $content));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->tokenFor($this->members), $content));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($this->members, $this->committee), $content));
    }

    public function testContentOpenToSeveralGroups(): void
    {
        $content = $this->content(Visibility::Groups, $this->members, $this->committee);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($this->members), $content));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($this->committee), $content));
    }

    public function testAdministratorsSeeEverything(): void
    {
        $admins = (new Group('admin', 'Administrateur'))->setAllPermissions(true);
        $content = $this->content(Visibility::Groups, $this->committee);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($admins), $content));
    }

    public function testIncludedGroupsGiveAccess(): void
    {
        $this->committee->addIncludedGroup($this->members);
        $content = $this->content(Visibility::Groups, $this->members);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->tokenFor($this->committee), $content), 'The committee includes the members');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->tokenFor($this->members), $this->content(Visibility::Groups, $this->committee)), 'Not the other way round');
    }

    public function testAnnouncedContentIsListedForEveryonePrivateContentForItsAudienceOnly(): void
    {
        $voter = new ContentVoter();
        $announced = $this->contentAnnounced(true, Visibility::Groups, $this->committee);
        $private = $this->contentAnnounced(false, Visibility::Groups, $this->committee);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $announced, [ContentVoter::LIST]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), $announced, [ContentVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor($this->members), $private, [ContentVoter::LIST]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor($this->committee), $private, [ContentVoter::LIST]));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new ContentVoter())->vote($this->tokenFor(), new \stdClass(), [ContentVoter::VIEW]));
    }

    private function vote(TokenInterface $token, RestrictedContentInterface $content): int
    {
        return (new ContentVoter())->vote($token, $content, [ContentVoter::VIEW]);
    }

    private function tokenFor(Group ...$groups): UsernamePasswordToken
    {
        $user = (new User())->setEmail('jane@example.org');
        foreach ($groups as $group) {
            $user->addGroup($group);
        }

        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function content(Visibility $visibility, Group ...$groups): RestrictedContentInterface
    {
        return $this->contentAnnounced(true, $visibility, ...$groups);
    }

    private function contentAnnounced(bool $announced, Visibility $visibility, Group ...$groups): RestrictedContentInterface
    {
        return new readonly class($visibility, $groups, $announced) implements RestrictedContentInterface {
            /**
             * @param list<Group> $groups
             */
            public function __construct(private Visibility $visibility, private array $groups, private bool $announced)
            {
            }

            public function isAnnounced(): bool
            {
                return $this->announced;
            }

            public function getVisibility(): Visibility
            {
                return $this->visibility;
            }

            public function getAllowedGroups(): iterable
            {
                return $this->groups;
            }
        };
    }
}
