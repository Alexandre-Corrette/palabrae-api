<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623143339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, operator_ref VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
        $this->addSql('CREATE TABLE coaching_record (id SERIAL NOT NULL, deviation_id INT NOT NULL, lesson_served_id INT DEFAULT NULL, operator_ref VARCHAR(64) NOT NULL, acknowledged BOOLEAN NOT NULL, purpose VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, purge_after TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A471A6BE8931F43 ON coaching_record (deviation_id)');
        $this->addSql('CREATE INDEX IDX_A471A6BE915017E ON coaching_record (lesson_served_id)');
        $this->addSql('COMMENT ON COLUMN coaching_record.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN coaching_record.purge_after IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE control_point (id SERIAL NOT NULL, procedure_id INT NOT NULL, lesson_id INT DEFAULT NULL, code VARCHAR(64) NOT NULL, label VARCHAR(255) NOT NULL, severity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C5C5A791624BCD2 ON control_point (procedure_id)');
        $this->addSql('CREATE INDEX IDX_C5C5A79CDF80196 ON control_point (lesson_id)');
        $this->addSql('CREATE TABLE deviation (id SERIAL NOT NULL, control_point_id INT NOT NULL, detected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note TEXT DEFAULT NULL, operator_ref VARCHAR(64) DEFAULT NULL, resolved BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_90A873F41FE83EE2 ON deviation (control_point_id)');
        $this->addSql('COMMENT ON COLUMN deviation.detected_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE integrity_log (id SERIAL NOT NULL, seq INT NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(48) NOT NULL, payload_hash VARCHAR(64) NOT NULL, prev_hash VARCHAR(64) NOT NULL, entry_hash VARCHAR(64) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_717CD79B967ED153 ON integrity_log (seq)');
        $this->addSql('COMMENT ON COLUMN integrity_log.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE investigation (id SERIAL NOT NULL, reference VARCHAR(128) NOT NULL, label VARCHAR(255) NOT NULL, site_ref VARCHAR(64) NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C3A58AA6AEA34913 ON investigation (reference)');
        $this->addSql('COMMENT ON COLUMN investigation.opened_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN investigation.closed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE micro_lesson (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, why TEXT NOT NULL, how TEXT NOT NULL, estimated_seconds INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE spotcheck_plan (id SERIAL NOT NULL, window_ref VARCHAR(128) NOT NULL, site_ref VARCHAR(64) NOT NULL, count INT NOT NULL, commitment VARCHAR(64) NOT NULL, algo VARCHAR(16) NOT NULL, status VARCHAR(255) NOT NULL, sealed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revealed_seed VARCHAR(128) DEFAULT NULL, revealed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_322C065F77576202 ON spotcheck_plan (window_ref)');
        $this->addSql('COMMENT ON COLUMN spotcheck_plan.sealed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN spotcheck_plan.revealed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE spotcheck_slot (id SERIAL NOT NULL, plan_id INT NOT NULL, ordinal INT NOT NULL, status VARCHAR(255) NOT NULL, honored_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, operator_ref VARCHAR(64) DEFAULT NULL, control_point_code VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_43787D45E899029B ON spotcheck_slot (plan_id)');
        $this->addSql('COMMENT ON COLUMN spotcheck_slot.honored_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE coaching_record ADD CONSTRAINT FK_A471A6BE8931F43 FOREIGN KEY (deviation_id) REFERENCES deviation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE coaching_record ADD CONSTRAINT FK_A471A6BE915017E FOREIGN KEY (lesson_served_id) REFERENCES micro_lesson (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE control_point ADD CONSTRAINT FK_C5C5A791624BCD2 FOREIGN KEY (procedure_id) REFERENCES investigation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE control_point ADD CONSTRAINT FK_C5C5A79CDF80196 FOREIGN KEY (lesson_id) REFERENCES micro_lesson (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE deviation ADD CONSTRAINT FK_90A873F41FE83EE2 FOREIGN KEY (control_point_id) REFERENCES control_point (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE spotcheck_slot ADD CONSTRAINT FK_43787D45E899029B FOREIGN KEY (plan_id) REFERENCES spotcheck_plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE coaching_record DROP CONSTRAINT FK_A471A6BE8931F43');
        $this->addSql('ALTER TABLE coaching_record DROP CONSTRAINT FK_A471A6BE915017E');
        $this->addSql('ALTER TABLE control_point DROP CONSTRAINT FK_C5C5A791624BCD2');
        $this->addSql('ALTER TABLE control_point DROP CONSTRAINT FK_C5C5A79CDF80196');
        $this->addSql('ALTER TABLE deviation DROP CONSTRAINT FK_90A873F41FE83EE2');
        $this->addSql('ALTER TABLE spotcheck_slot DROP CONSTRAINT FK_43787D45E899029B');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE coaching_record');
        $this->addSql('DROP TABLE control_point');
        $this->addSql('DROP TABLE deviation');
        $this->addSql('DROP TABLE integrity_log');
        $this->addSql('DROP TABLE investigation');
        $this->addSql('DROP TABLE micro_lesson');
        $this->addSql('DROP TABLE spotcheck_plan');
        $this->addSql('DROP TABLE spotcheck_slot');
    }
}
