<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename uset_at to used_at and add unique constraint on token in user_invitation_token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_invitation_token RENAME COLUMN uset_at TO used_at');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_INVITATION_TOKEN ON user_invitation_token (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_INVITATION_TOKEN');
        $this->addSql('ALTER TABLE user_invitation_token RENAME COLUMN used_at TO uset_at');
    }
}
