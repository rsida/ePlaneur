<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008213917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Memberships: online payment with HelloAsso Checkout';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership ADD checkout_intent_id VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership DROP checkout_intent_id');
    }
}
