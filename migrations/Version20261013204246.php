<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Exercises checked by the analysis (roadmap 11b): criteria, imposed glider and ranking measure of
 * the items of "Mon parcours".
 */
final class Version20261013204246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mon parcours: criteria, glider and ranking measure of the exercises';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE path_item ADD criteria JSON NOT NULL, ADD glider VARCHAR(60) DEFAULT NULL, ADD rank_by VARCHAR(30) NOT NULL');
        // Existing exercises keep asking for a finished task, ranked by task speed
        $this->addSql("UPDATE path_item SET criteria = '[]', rank_by = 'task_speed'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE path_item DROP criteria, DROP glider, DROP rank_by');
    }
}
