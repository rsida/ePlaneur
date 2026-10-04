<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows or changes the groups of an account:
 * `make console c="app:user:groups jane@example.org --add=committee --remove=member"`.
 */
#[AsCommand(name: 'app:user:groups', description: 'Show, add or remove the groups of an account')]
final readonly class UserGroupsCommand
{
    public function __construct(
        private UserRepository $users,
        private GroupRepository $groups,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $add
     * @param list<string> $remove
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail address of the account')] string $email,
        #[Option('Group code to add (repeatable)')] array $add = [],
        #[Option('Group code to remove (repeatable)')] array $remove = [],
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('No account for %s.', $email));

            return Command::FAILURE;
        }

        foreach (['add' => $add, 'remove' => $remove] as $action => $codes) {
            foreach ($codes as $code) {
                $group = $this->groups->findOneByCode($code);
                if (null === $group) {
                    $io->error(\sprintf('Unknown group "%s". Run app:group:list to see the codes.', $code));

                    return Command::FAILURE;
                }
                'add' === $action ? $user->addGroup($group) : $user->removeGroup($group);
            }
        }
        $this->entityManager->flush();

        $names = $user->getGroups()->map(static fn ($group): string => \sprintf('%s (%s)', $group->getName(), $group->getCode()))->toArray();
        $io->writeln(\sprintf('%s: %s', $email, [] === $names ? 'no group' : implode(', ', $names)));

        return Command::SUCCESS;
    }
}
