<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GH-197 — Boîte noire : append-only renforcé EN BASE.
 *
 * Le code interdit déjà toute mutation (IntegrityLogEntry n'a aucun setter),
 * mais l'ORM n'empêche pas un UPDATE/DELETE SQL direct. On pose donc un trigger
 * PL/pgSQL qui lève une exception sur toute tentative de modification ou de
 * suppression d'un maillon : le journal devient strictement append-only au
 * niveau du SGBD.
 *
 * Pourquoi un trigger plutôt qu'un REVOKE : l'application se connecte comme
 * PROPRIÉTAIRE de la table, et le propriétaire conserve implicitement ses
 * privilèges malgré un REVOKE. Le trigger, lui, s'applique à toutes les
 * sessions (y compris le propriétaire). Pour un durcissement supplémentaire,
 * faire tourner l'app sous un rôle dédié NON propriétaire et ajouter :
 *   REVOKE UPDATE, DELETE ON integrity_log FROM <role_applicatif>;
 *
 * PostgreSQL uniquement.
 */
final class Version20260623160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GH-197 : trigger append-only sur integrity_log (interdit UPDATE/DELETE).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration PostgreSQL uniquement (trigger PL/pgSQL).',
        );

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION integrity_log_append_only_guard()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'integrity_log est append-only : % interdit.', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_integrity_log_append_only
                BEFORE UPDATE OR DELETE ON integrity_log
                FOR EACH ROW EXECUTE FUNCTION integrity_log_append_only_guard()
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration PostgreSQL uniquement (trigger PL/pgSQL).',
        );

        $this->addSql('DROP TRIGGER IF EXISTS trg_integrity_log_append_only ON integrity_log');
        $this->addSql('DROP FUNCTION IF EXISTS integrity_log_append_only_guard()');
    }
}
