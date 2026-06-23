<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GH-204 — Table `inspection` : trace de chaque contrôle (conforme ou non),
 * avec sa source (déclaré / contrôle surprise / capteur).
 */
final class Version20260623170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GH-204 : création de la table inspection (contrôles + source).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration PostgreSQL uniquement.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inspection (
                id SERIAL NOT NULL,
                control_point_id INT NOT NULL,
                outcome VARCHAR(255) NOT NULL,
                source VARCHAR(255) NOT NULL,
                operator_ref VARCHAR(64) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_inspection_control_point ON inspection (control_point_id)');
        $this->addSql("COMMENT ON COLUMN inspection.recorded_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql(<<<'SQL'
            ALTER TABLE inspection
                ADD CONSTRAINT FK_inspection_control_point
                FOREIGN KEY (control_point_id) REFERENCES control_point (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration PostgreSQL uniquement.',
        );

        $this->addSql('ALTER TABLE inspection DROP CONSTRAINT FK_inspection_control_point');
        $this->addSql('DROP TABLE inspection');
    }
}
