<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GH-207 — Flag « preuve photo requise » par point de contrôle.
 */
final class Version20260624100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GH-207 : control_point.requires_photo (preuve photo exigée).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE control_point ADD requires_photo BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE control_point DROP requires_photo');
    }
}
