<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Membership seasons, types and requests (roadmap step 8a); the committee manages them.
 */
final class Version20261008212439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Membership seasons, types and requests; grant MEMBERSHIP_MANAGE to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, fee INT NOT NULL, donation INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, birth_date DATE DEFAULT NULL, address VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(100) NOT NULL, country VARCHAR(2) NOT NULL, phone VARCHAR(30) NOT NULL, rules_accepted TINYINT NOT NULL, requested_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, payment_method VARCHAR(20) DEFAULT NULL, payment_reference VARCHAR(100) DEFAULT NULL, validated_at DATETIME DEFAULT NULL, note LONGTEXT DEFAULT NULL, reminder_sent_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, season_id INT NOT NULL, type_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, INDEX IDX_86FFD2857B00651C (status), INDEX IDX_86FFD285A76ED395 (user_id), INDEX IDX_86FFD2854EC001D1 (season_id), INDEX IDX_86FFD285C54C8C93 (type_id), INDEX IDX_86FFD285C69DE5E5 (validated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membership_season (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, open TINYINT NOT NULL, payment_instructions LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_8E59EA30BB827337 (year), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membership_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, price INT NOT NULL, max_age INT DEFAULT NULL, position INT NOT NULL, season_id INT DEFAULT NULL, INDEX IDX_F7E162E24EC001D1 (season_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD2854EC001D1 FOREIGN KEY (season_id) REFERENCES membership_season (id)');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285C54C8C93 FOREIGN KEY (type_id) REFERENCES membership_type (id)');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE membership_type ADD CONSTRAINT FK_F7E162E24EC001D1 FOREIGN KEY (season_id) REFERENCES membership_season (id) ON DELETE CASCADE');
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'MEMBERSHIP_MANAGE')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"MEMBERSHIP_MANAGE"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'MEMBERSHIP_MANAGE')))
            WHERE code = 'committee' AND JSON_CONTAINS(permissions, '"MEMBERSHIP_MANAGE"')
            SQL);
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285A76ED395');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD2854EC001D1');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285C54C8C93');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285C69DE5E5');
        $this->addSql('ALTER TABLE membership_type DROP FOREIGN KEY FK_F7E162E24EC001D1');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE membership_season');
        $this->addSql('DROP TABLE membership_type');
    }
}
