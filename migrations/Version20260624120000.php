<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contrôle à réception multi-lignes : un bon de livraison (reception) contient
 * plusieurs lignes produit (reception_line).
 */
final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'reception (BL) + reception_line (produits contrôlés).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE reception (
                id SERIAL NOT NULL,
                control_point_id INT NOT NULL,
                bl_number VARCHAR(64) NOT NULL,
                operator_ref VARCHAR(64) DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                outcome VARCHAR(255) NOT NULL,
                severity INT DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_reception_control_point ON reception (control_point_id)');
        $this->addSql("COMMENT ON COLUMN reception.recorded_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql(<<<'SQL'
            ALTER TABLE reception
                ADD CONSTRAINT FK_reception_control_point
                FOREIGN KEY (control_point_id) REFERENCES control_point (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE reception_line (
                id SERIAL NOT NULL,
                reception_id INT NOT NULL,
                product_label VARCHAR(191) NOT NULL,
                product_code VARCHAR(64) DEFAULT NULL,
                results JSON NOT NULL,
                conform BOOLEAN NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_reception_line_reception ON reception_line (reception_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE reception_line
                ADD CONSTRAINT FK_reception_line_reception
                FOREIGN KEY (reception_id) REFERENCES reception (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reception_line DROP CONSTRAINT FK_reception_line_reception');
        $this->addSql('ALTER TABLE reception DROP CONSTRAINT FK_reception_control_point');
        $this->addSql('DROP TABLE reception_line');
        $this->addSql('DROP TABLE reception');
    }
}
