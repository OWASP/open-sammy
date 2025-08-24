<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250728110352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type field to metamodel and prepend metamodel ID to external_id fields for entities connected to metamodel ID 2';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `metamodel` ADD `type` INT NOT NULL DEFAULT 0');

        $this->addSql('UPDATE `metamodel` SET `type` = 0 WHERE `id` = 1');
        $this->addSql('UPDATE `metamodel` SET `type` = 1 WHERE `id` != 1');

        // Prepend metamodel ID '2' to external_id fields for entities connected to metamodel ID 2
        // Note: practice_level is special - it gets practice.external_id + 'x' + maturity_level.external_id

        // 1. Update business_function (has direct metamodel_id)
        $this->addSql("
            UPDATE business_function 
            SET external_id = CONCAT('2', external_id) 
            WHERE metamodel_id = 2 
            AND external_id IS NOT NULL
        ");

        // 2. Update practice (via business_function)
        $this->addSql("
            UPDATE practice p
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET p.external_id = CONCAT('2', p.external_id)
            WHERE bf.metamodel_id = 2 
            AND p.external_id IS NOT NULL
        ");

        // 3. Update stream (via practice -> business_function)
        $this->addSql("
            UPDATE stream s
            INNER JOIN practice p ON s.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET s.external_id = CONCAT('2', s.external_id)
            WHERE bf.metamodel_id = 2 
            AND s.external_id IS NOT NULL
        ");

        // 4. Update maturity_level (via practice_level -> practice -> business_function)
        $this->addSql("
            UPDATE maturity_level ml
            INNER JOIN practice_level pl ON pl.maturity_level_id = ml.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET ml.external_id = CONCAT('2', ml.external_id)
            WHERE bf.metamodel_id = 2 
            AND ml.external_id IS NOT NULL
        ");

        // 5. Update practice_level (special case: practice external_id + 'x' + maturity_level external_id)
        // Note: Both practice and maturity_level have already been updated with '2' prefix
        $this->addSql("
            UPDATE practice_level pl
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN maturity_level ml ON pl.maturity_level_id = ml.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET pl.external_id = CONCAT(p.external_id, 'x', ml.external_id)
            WHERE bf.metamodel_id = 2 
            AND pl.external_id IS NOT NULL
            AND p.external_id IS NOT NULL
            AND ml.external_id IS NOT NULL
        ");

        // 6. Update activity (via practice_level -> practice -> business_function)
        $this->addSql("
            UPDATE activity a
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET a.external_id = CONCAT('2', a.external_id)
            WHERE bf.metamodel_id = 2 
            AND a.external_id IS NOT NULL
        ");

        // 6. Update question (via activity -> practice_level -> practice -> business_function)
        $this->addSql("
            UPDATE question q
            INNER JOIN activity a ON q.activity_id = a.id
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET q.external_id = CONCAT('2', q.external_id)
            WHERE bf.metamodel_id = 2 
            AND q.external_id IS NOT NULL
        ");

        // 7. Update answer_set (via question -> activity -> practice_level -> practice -> business_function)
        $this->addSql("
            UPDATE answer_set ans
            INNER JOIN question q ON q.answer_set_id = ans.id
            INNER JOIN activity a ON q.activity_id = a.id
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET ans.external_id = CONCAT('2', ans.external_id)
            WHERE bf.metamodel_id = 2 
            AND ans.external_id IS NOT NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `metamodel` DROP `type`');

        // Revert external_id changes

        // 1. Revert business_function
        $this->addSql("
            UPDATE business_function 
            SET external_id = SUBSTRING(external_id, 2) 
            WHERE metamodel_id = 2 
            AND external_id IS NOT NULL 
            AND external_id LIKE '2%'
        ");

        // 2. Revert practice
        $this->addSql("
            UPDATE practice p
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET p.external_id = SUBSTRING(p.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND p.external_id IS NOT NULL 
            AND p.external_id LIKE '2%'
        ");

        // 3. Revert stream
        $this->addSql("
            UPDATE stream s
            INNER JOIN practice p ON s.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET s.external_id = SUBSTRING(s.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND s.external_id IS NOT NULL 
            AND s.external_id LIKE '2%'
        ");

        // 4. Revert maturity_level
        $this->addSql("
            UPDATE maturity_level ml
            INNER JOIN practice_level pl ON pl.maturity_level_id = ml.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET ml.external_id = SUBSTRING(ml.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND ml.external_id IS NOT NULL 
            AND ml.external_id LIKE '2%'
        ");

        // 5. Revert practice_level
        // NOTE: Since practice_level external_id was completely replaced with a concatenation,
        // we cannot restore the original value. This sets it to null instead.
        // practice_level is special - it doesn't have '2' prefix like other entities
        $this->addSql("
            UPDATE practice_level pl
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET pl.external_id = NULL
            WHERE bf.metamodel_id = 2 
            AND pl.external_id IS NOT NULL
        ");

        // 6. Revert activity
        $this->addSql("
            UPDATE activity a
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET a.external_id = SUBSTRING(a.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND a.external_id IS NOT NULL 
            AND a.external_id LIKE '2%'
        ");

        // 6. Revert question
        $this->addSql("
            UPDATE question q
            INNER JOIN activity a ON q.activity_id = a.id
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET q.external_id = SUBSTRING(q.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND q.external_id IS NOT NULL 
            AND q.external_id LIKE '2%'
        ");

        // 7. Revert answer_set
        $this->addSql("
            UPDATE answer_set ans
            INNER JOIN question q ON q.answer_set_id = ans.id
            INNER JOIN activity a ON q.activity_id = a.id
            INNER JOIN practice_level pl ON a.practice_level_id = pl.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET ans.external_id = SUBSTRING(ans.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND ans.external_id IS NOT NULL 
            AND ans.external_id LIKE '2%'
        ");

        // 8. Revert maturity_level
        $this->addSql("
            UPDATE maturity_level ml
            INNER JOIN practice_level pl ON pl.maturity_level_id = ml.id
            INNER JOIN practice p ON pl.practice_id = p.id
            INNER JOIN business_function bf ON p.business_function_id = bf.id
            SET ml.external_id = SUBSTRING(ml.external_id, 2)
            WHERE bf.metamodel_id = 2 
            AND ml.external_id IS NOT NULL 
            AND ml.external_id LIKE '2%'
        ");
    }
}