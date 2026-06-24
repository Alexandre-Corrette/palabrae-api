<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Critères de contrôle (cases d'une fiche → règle métier) + gravité effective
 * d'un écart issu de l'évaluation des critères.
 */
final class Version20260624110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'control_criterion (critères + règles) + deviation.severity_override.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE control_criterion (
                id SERIAL NOT NULL,
                control_point_id INT NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL,
                severity INT NOT NULL,
                comparator VARCHAR(255) DEFAULT NULL,
                threshold DOUBLE PRECISION DEFAULT NULL,
                unit VARCHAR(16) DEFAULT NULL,
                position INT NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_criterion_control_point ON control_criterion (control_point_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE control_criterion
                ADD CONSTRAINT FK_criterion_control_point
                FOREIGN KEY (control_point_id) REFERENCES control_point (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        $this->addSql('ALTER TABLE deviation ADD severity_override INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deviation DROP severity_override');
        $this->addSql('ALTER TABLE control_criterion DROP CONSTRAINT FK_criterion_control_point');
        $this->addSql('DROP TABLE control_criterion');
    }
}
