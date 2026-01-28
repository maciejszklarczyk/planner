<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127184623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_has_group ADD added_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_has_group ADD CONSTRAINT FK_96A9D99F55B127A4 FOREIGN KEY (added_by_id) REFERENCES "user" (id)');
        $this->addSql('CREATE INDEX IDX_96A9D99F55B127A4 ON user_has_group (added_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_has_group DROP CONSTRAINT FK_96A9D99F55B127A4');
        $this->addSql('DROP INDEX IDX_96A9D99F55B127A4');
        $this->addSql('ALTER TABLE user_has_group DROP added_by_id');
    }
}
