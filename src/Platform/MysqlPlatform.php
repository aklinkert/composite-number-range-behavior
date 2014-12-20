<?php

namespace APinnecke\CompositeNumberRange\Platform;

use APinnecke\CompositeNumberRange\CompositeNumberRangeBehavior;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\MysqlPlatform as BaseMysqlPlatform;

class MysqlPlatform extends BaseMysqlPlatform
{
    const BEHAVIOR_NAME = 'APinnecke\CompositeNumberRange\CompositeNumberRangeBehavior';

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
        if ($table->hasBehavior(self::BEHAVIOR_NAME)) {

            if ($table->hasBehavior('concrete_inheritance')) {
                // we're a child in a concrete inheritance
                $parentTableName = $table->getBehavior('concrete_inheritance')->getParameter('extends');
                $parentTable = $table->getDatabase()->getTable($parentTableName);

                if ($parentTable->hasBehavior(self::BEHAVIOR_NAME)) {
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
        $ret = parent::getAddTableDDL($table);

        if ($this->hasCompositeNumberRangeBehavior($table)) {
            $ret .= $this->createTriggerDDL($table);
        }

        echo $ret;

        return $ret;
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
        $behavior = $table->getBehavior(self::BEHAVIOR_NAME);
        $foreignTableName = $behavior->getForeignTable();

        return str_replace(' ', '', ucwords(str_replace('_', ' ', 'set' . ucfirst($foreignTableName) . ucfirst($tableName) . 'Id')));
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

        /** @var CompositeNumberRangeBehavior $behavior */
        $behavior = $table->getBehavior(self::BEHAVIOR_NAME);
        $foreignTableName = $behavior->getForeignTable();
        $triggerName = $this->getTriggerName($table);
        $tableName = $table->getName();

        $sql = "
DELIMITER $;

CREATE TRIGGER {$triggerName}
BEFORE INSERT ON ${tableName}
FOR EACH ROW
BEGIN
    INSERT INTO ${foreignTableName}_sequence (
        table_name, ${foreignTableName}_id, ${foreignTableName}_max_sub_id
    ) VALUES (
        '${tableName}', NEW.${foreignTableName}_id, LAST_INSERT_ID(1)
    ) ON DUPLICATE KEY
        UPDATE ${foreignTableName}_max_sub_id = LAST_INSERT_ID(${foreignTableName}_max_sub_id +1);

    SET NEW.${foreignTableName}_${tableName}_id = LAST_INSERT_ID();
END

DELIMITER ;
";

        return $sql;
    }

} 