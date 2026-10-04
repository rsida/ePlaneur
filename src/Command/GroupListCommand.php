<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GroupRepository;
use App\Security\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:group:list', description: 'List the groups, their permissions and member count')]
final readonly class GroupListCommand
{
    public function __construct(
        private GroupRepository $groups,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $rows = [];
        foreach ($this->groups->findAllOrdered() as $group) {
            $permissions = $group->hasAllPermissions()
                ? 'all'
                : implode(', ', array_map(static fn (Permission $permission): string => $permission->value, $group->getPermissions()));
            $rows[] = [$group->getCode(), $group->getName(), $permissions ?: '-', $group->getUsers()->count()];
        }
        $io->table(['Code', 'Name', 'Permissions', 'Members'], $rows);

        $io->section('Available permissions');
        $io->listing(array_map(static fn (Permission $permission): string => \sprintf('%s: %s', $permission->value, $permission->label()), Permission::cases()));

        return Command::SUCCESS;
    }
}
