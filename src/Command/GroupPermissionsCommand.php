<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GroupRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Grants or revokes permissions of a group:
 * `make console c="app:group:permissions committee --grant=USER_MANAGE"`.
 */
#[AsCommand(name: 'app:group:permissions', description: 'Grant or revoke permissions of a group')]
final readonly class GroupPermissionsCommand
{
    public function __construct(
        private GroupRepository $groups,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $grant
     * @param list<string> $revoke
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Group code')] string $code,
        #[Option('Permission to grant (repeatable)')] array $grant = [],
        #[Option('Permission to revoke (repeatable)')] array $revoke = [],
    ): int {
        $group = $this->groups->findOneByCode($code);
        if (null === $group) {
            $io->error(\sprintf('Unknown group "%s".', $code));

            return Command::FAILURE;
        }

        foreach (['grant' => $grant, 'revoke' => $revoke] as $action => $values) {
            foreach ($values as $value) {
                $permission = Permission::tryFrom(strtoupper($value));
                if (null === $permission) {
                    $io->error(\sprintf('Unknown permission "%s". Run app:group:list to see them.', $value));

                    return Command::FAILURE;
                }
                'grant' === $action ? $group->grant($permission) : $group->revoke($permission);
            }
        }
        $this->entityManager->flush();

        $io->writeln(\sprintf('%s: %s', $group->getCode(), $group->hasAllPermissions()
            ? 'all permissions'
            : (implode(', ', array_map(static fn (Permission $permission): string => $permission->value, $group->getPermissions())) ?: 'no permission')));

        return Command::SUCCESS;
    }
}
