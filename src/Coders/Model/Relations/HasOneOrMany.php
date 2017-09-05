<?php

/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace ILazi\Coders\Model\Relations;

use ILazi\Coders\Model\Factory;
use ILazi\Support\Dumper;
use Illuminate\Support\Fluent;
use ILazi\Coders\Model\Model;
use ILazi\Coders\Model\Relation;

abstract class HasOneOrMany implements Relation
{
    /**
     * @var \Illuminate\Support\Fluent
     */
    protected $command;

    /**
     * @var \ILazi\Coders\Model\Model
     */
    protected $parent;

    /**
     * @var \ILazi\Coders\Model\Model
     */
    protected $related;

    /**
     * HasManyWriter constructor.
     *
     * @param \Illuminate\Support\Fluent $command
     * @param \ILazi\Coders\Model\Model $parent
     * @param \ILazi\Coders\Model\Model $related
     */
    public function __construct(Fluent $command, Model $parent, Model $related)
    {
        $this->command = $command;
        $this->parent = $parent;
        $this->related = $related;
    }

    /**
     * @return string
     */
    abstract public function hint();

    /**
     * @return string
     */
    abstract public function name();

    /**
     * @return string
     */
    public function body()
    {
        $body = 'return $this->'.$this->method().'(';

        $body .= $this->related->getClassName().'::class';

        if ($this->needsForeignKey()) {
            $body .= ', '.Dumper::export($this->foreignKey());
        }

        if ($this->needsLocalKey()) {
            $body .= ', '.Dumper::export($this->localKey());
        }

        $body .= ');';

        return $body;
    }

    public function rGetMethod()
    {
        return 'where is this shit?';
    }

    public function getDoc($level = "\n\t")
    {
        return 'where is this shit2?';
    }

    public function rBody($level = "\n\t")
    {
        $body[] = '/** @noinspection PhpUndefinedFieldInspection */';
        $body[] = 'return $this->'.str_replace('fk_', '', $this->foreignKey()).';';
        return implode($level, $body);
    }

    /**
     * @return string
     */
    public function getRelatedClass()
    {
        return $this->related->getClassName();
    }

    /**
     * @return string
     */
    abstract protected function method();

    /**
     * @return bool
     */
    protected function needsForeignKey()
    {
        $defaultForeignKey = $this->parent->getRecordName().'_id';

        return $defaultForeignKey != $this->foreignKey() || $this->needsLocalKey();
    }

    /**
     * @return string
     */
    protected function foreignKey()
    {
        return $this->command->columns[0];
    }

    /**
     * @return bool
     */
    protected function needsLocalKey()
    {
        return $this->parent->getPrimaryKey() != $this->localKey();
    }

    /**
     * @return string
     */
    protected function localKey()
    {
        return $this->command->references[0];
    }

}
