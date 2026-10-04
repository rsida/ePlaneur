<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Form\NewPasswordType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Security\AccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates an account from the command line, e.g. the first administrator:
 * `make console c="app:user:create admin@example.org 'Jane Doe' --group=admin"`.
 */
#[AsCommand(name: 'app:user:create', description: 'Create a verified account, optionally in some groups')]
final readonly class UserCreateCommand
{
    public function __construct(
        private UserRepository $users,
        private GroupRepository $groups,
        private AccountManager $accountManager,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param list<string> $group
     */
    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('E-mail address (login)')] string $email,
        #[Argument('Name shown on the site')] string $displayName,
        #[Option('Group code to add the user to (repeatable)')] array $group = [],
    ): int {
        if (null !== $this->users->findOneByEmail($email)) {
            $io->error(\sprintf('An account already exists for %s.', $email));

            return Command::FAILURE;
        }

        $user = (new User())->setEmail($email)->setDisplayName($displayName)->setVerified(true);
        foreach ($group as $code) {
            $found = $this->groups->findOneByCode($code);
            if (null === $found) {
                $io->error(\sprintf('Unknown group "%s". Run app:group:list to see the codes.', $code));

                return Command::FAILURE;
            }
            $user->addGroup($found);
        }

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath().': '.$violation->getMessage());
            }

            return Command::FAILURE;
        }

        $password = $input->isInteractive() ? $this->askPassword($io) : null;
        // Without a password (non-interactive run), the user sets one with "Mot de passe oublié"
        $this->accountManager->changePassword($user, $password ?? bin2hex(random_bytes(32)), flush: false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Account created for %s%s.', $email, null === $password ? ' (no usable password: use "Mot de passe oublié")' : ''));

        return Command::SUCCESS;
    }

    private function askPassword(SymfonyStyle $io): string
    {
        return (string) $io->askHidden(\sprintf('Password (at least %d characters)', NewPasswordType::MIN_LENGTH), static function (?string $value): string {
            if (null === $value || mb_strlen($value) < NewPasswordType::MIN_LENGTH) {
                throw new \RuntimeException(\sprintf('At least %d characters.', NewPasswordType::MIN_LENGTH));
            }

            return $value;
        });
    }
}
