<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Network sessions (roadmap step 9f): flight plans, sessions and their results, tracks joined to
 * their session; members organise sessions, the committee manages them all.
 */
final class Version20261009150222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flight plans and network sessions; grant SESSION_ORGANISE to members and SESSION_MANAGE to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE flight_plan (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, title VARCHAR(160) DEFAULT NULL, gliders VARCHAR(40) DEFAULT NULL, landscape VARCHAR(40) DEFAULT NULL, sheet_url VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_5C64061A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE network_session (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, start_time TIME DEFAULT NULL, dedicated_to VARCHAR(60) DEFAULT NULL, condor_club_url VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, race_id BIGINT DEFAULT NULL, results_file VARCHAR(80) DEFAULT NULL, results JSON NOT NULL, created_at DATETIME NOT NULL, flight_plan_id INT NOT NULL, organiser_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_74F702BE3020D4CA (results_file), INDEX IDX_74F702BEAA9E377A (date), INDEX IDX_74F702BE6E59D40D (race_id), INDEX IDX_74F702BE6B75E10D (flight_plan_id), INDEX IDX_74F702BEA0631C12 (organiser_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE network_session ADD CONSTRAINT FK_74F702BE6B75E10D FOREIGN KEY (flight_plan_id) REFERENCES flight_plan (id)');
        $this->addSql('ALTER TABLE network_session ADD CONSTRAINT FK_74F702BEA0631C12 FOREIGN KEY (organiser_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flight ADD session_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE flight ADD CONSTRAINT FK_C257E60E613FECDF FOREIGN KEY (session_id) REFERENCES network_session (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C257E60E613FECDF ON flight (session_id)');
        foreach (['member' => 'SESSION_ORGANISE', 'committee' => 'SESSION_MANAGE'] as $group => $permission) {
            $this->addSql(<<<'SQL'
                UPDATE app_group
                SET permissions = JSON_ARRAY_APPEND(permissions, '$', :permission)
                WHERE code = :group AND NOT JSON_CONTAINS(permissions, JSON_QUOTE(:permission))
                SQL, ['group' => $group, 'permission' => $permission]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['member' => 'SESSION_ORGANISE', 'committee' => 'SESSION_MANAGE'] as $group => $permission) {
            $this->addSql(<<<'SQL'
                UPDATE app_group
                SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', :permission)))
                WHERE code = :group AND JSON_CONTAINS(permissions, JSON_QUOTE(:permission))
                SQL, ['group' => $group, 'permission' => $permission]);
        }
        $this->addSql('ALTER TABLE flight DROP FOREIGN KEY FK_C257E60E613FECDF');
        $this->addSql('DROP INDEX IDX_C257E60E613FECDF ON flight');
        $this->addSql('ALTER TABLE flight DROP session_id');
        $this->addSql('ALTER TABLE network_session DROP FOREIGN KEY FK_74F702BE6B75E10D');
        $this->addSql('ALTER TABLE network_session DROP FOREIGN KEY FK_74F702BEA0631C12');
        $this->addSql('DROP TABLE network_session');
        $this->addSql('DROP TABLE flight_plan');
    }
}
