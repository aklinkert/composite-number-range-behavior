<?php
/**
 * User: Alexander Pinnecke <alexander.pinnecke@finanzcheck.de>
 * Date: 11.10.14
 */

namespace APinnecke\CompositeNumberRange;

use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\MysqlPlatform;

class CompositeNumberRangeMysqlPlatform extends MysqlPlatform
{
    const BEHAVIOR_CLASS = '\APinnecke\CompositeNumberRange\CompositeNumberRangeBehavior';

    /**
     * {@inheritdoc}
     */
    public function getModifyTableDDL(TableDiff $tableDiff)
    {
        $ret = parent::getModifyTableDDL($tableDiff);

        $fromTable = $tableDiff->getFromTable();
        $toTable = $tableDiff->getToTable();

        $hasTrigger = $this->hasTrigger($fromTable);

        // if from-table has a trigger but to-table don't need it anymore, then drop it
        $needDrop = $hasTrigger && !$this->hasCompositeNumberRangeBehavior($toTable);

        // if from-table has no trigger but to-table wants one, create it
        $needCreate = !$hasTrigger && $this->hasCompositeNumberRangeBehavior($toTable);

        switch (true) {
            case $needCreate:
                $ret .= $this->createTriggerDDL($toTable);
                break;
            case $needDrop:
                $ret .= $this->createDropTriggerDDL($toTable);
                break;
        }

        return $ret;
    }

    /**
     * Returns the actual trigger name in the database. Handy if you don't have the behavior definition
     * on a table.
     *
     * @param Table $table
     *
     * @return string|null
     */
    protected function getExistingTriggerName(Table $table)
    {
        $con = $this->getConnection();

        $sql = "SHOW TRIGGERS WHERE `Table` = ? AND `Trigger` LIKE 'set%Id'";
        $stmt = $con->prepare($sql);
        $stmt->execute([$table->getName()]);

        return $stmt->fetchColumn();
    }

    /**
     * Returns true if our trigger for given table exists.
     *
     * @param Table $table
     *
     * @return bool
     */
    protected function hasTrigger(Table $table)
    {
        return (boolean) $this->getExistingTriggerName($table);
    }

    /**
     * @param Table $table
     *
     * @return bool
     */
    protected function hasCompositeNumberRangeBehavior(Table $table)
    {
        if ($table->hasBehavior(static::BEHAVIOR_CLASS)) {

            if ($table->hasBehavior('concrete_inheritance')) {
                // we're a child in a concrete inheritance
                $parentTableName = $table->getBehavior('concrete_inheritance')->getParameter('extends');
                $parentTable = $table->getDatabase()->getTable($parentTableName);

                if ($parentTable->hasBehavior(static::BEHAVIOR_CLASS)) {
                    //we're a child of a concrete inheritance structure, so we're going to skip this
                    //round here because this behavior has also been attached by the parent table.
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddTableDDL(Table $table)
    {
        $ddl = parent::getAddTableDDL($table);

        if ($this->hasCompositeNumberRangeBehavior($table)) {
            $ddl .= $this->createTriggerDDL($table);
        }

        return $ddl;
    }

    /**
     * Returns the DQL to create a new trigger.
     *
     * @param Table $table
     *
     * @return string
     */
    protected function createTriggerDDL(Table $table)
    {
        $tableName = $table->getName();

        /** @var CompositeNumberRangeBehavior $behavior */
        $behavior = $table->getBehavior(static::BEHAVIOR_CLASS);
        $foreignTableName = $behavior->getForeignTable();

        $triggerName = $this->getTriggerName($table);

//        $sql = "
//CREATE TRIGGER ${triggerName}
//BEFORE INSERT ON ${tableName}
//FOR EACH ROW
//SET NEW.${foreignTableName}_${tableName}_id = (
//    SELECT IFNULL(MAX(${foreignTableName}_${tableName}_id), 0) + 1
//        FROM ${tableName}
//        WHERE ${foreignTableName}_id = NEW.${foreignTableName}_id
//);
//";

        $sql = "
CREATE TRIGGER {$triggerName}
BEFORE INSERT ON ${tableName}
FOR EACH ROW
BEGIN
    INSERT INTO ${foreignTableName}_sequence (
        table_name, ${foreignTableName}_id,${foreignTableName} _max_sub_id
    ) VALUES (
        '${tableName}', NEW.${foreignTableName}_id, LAST_INSERT_ID(1)
    ) ON DUPLICATE KEY
        UPDATE ${foreignTableName}_max_sub_id = LAST_INSERT_ID(${foreignTableName}_max_sub_id +1);

    SET NEW.${foreignTableName}_${tableName}_id = LAST_INSERT_ID();
END;
";

        return $sql;
    }

    /**
     * Returns the DQL to remove a trigger.
     *
     * @param Table $table
     *
     * @return string
     */
    protected function createDropTriggerDDL(Table $table)
    {
        $triggerName = $this->getExistingTriggerName($table);

        return "DROP TRIGGER IF EXISTS $triggerName;\n";
    }

    /**
     * Returns the trigger name.
     *
     * @param Table $table
     *
     * @return string
     */
    protected function getTriggerName(Table $table)
    {
        $tableName = $table->getName();

        /** @var CompositeNumberRangeBehavior $behavior */
        $behavior = $table->getBehavior(static::BEHAVIOR_CLASS);
        $foreignTableName = $behavior->getForeignTable();

        return 'set' . ucfirst($foreignTableName) . ucfirst($tableName) . 'Id';
    }
} 