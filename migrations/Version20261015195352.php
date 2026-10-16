<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Teacher space (roadmap 11f): classes of a teacher and the pupils who joined them.
 */
final class Version20261015195352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Classes of the teachers and their pupils';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE school_class (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, school VARCHAR(100) DEFAULT NULL, code VARCHAR(12) NOT NULL, open TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, teacher_id INT NOT NULL, UNIQUE INDEX UNIQ_33B1AF8577153098 (code), INDEX IDX_33B1AF8541807E1D (teacher_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE school_class_pupil (school_class_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_720AC13714463F54 (school_class_id), INDEX IDX_720AC137A76ED395 (user_id), PRIMARY KEY (school_class_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE school_class ADD CONSTRAINT FK_33B1AF8541807E1D FOREIGN KEY (teacher_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_class_pupil ADD CONSTRAINT FK_720AC13714463F54 FOREIGN KEY (school_class_id) REFERENCES school_class (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_class_pupil ADD CONSTRAINT FK_720AC137A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_class DROP FOREIGN KEY FK_33B1AF8541807E1D');
        $this->addSql('ALTER TABLE school_class_pupil DROP FOREIGN KEY FK_720AC13714463F54');
        $this->addSql('ALTER TABLE school_class_pupil DROP FOREIGN KEY FK_720AC137A76ED395');
        $this->addSql('DROP TABLE school_class');
        $this->addSql('DROP TABLE school_class_pupil');
    }
}
