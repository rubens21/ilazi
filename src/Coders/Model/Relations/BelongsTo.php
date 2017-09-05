<?php

/**
 * Created by Cristian.
 * Date: 05/09/16 11:41 PM.
 */

namespace ILazi\Coders\Model\Relations;

use Illuminate\Support\Str;
use ILazi\Coders\Model\Factory;
use ILazi\Support\Dumper;
use Illuminate\Support\Fluent;
use ILazi\Coders\Model\Model;
use ILazi\Coders\Model\Relation;

class BelongsTo implements Relation
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
    public $related;

    /**
     * BelongsToWriter constructor.
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
    public function name()
    {
        $name = str_replace('fk_', '', $this->foreignKey());
        if ($this->parent->usesSnakeAttributes()) {
            return Str::snake($name);
        }

        return Str::camel($name);
    }

    /**
     * @return string
     */
    public function body()
    {
        $body = 'return $this->belongsTo(';

        $body .= $this->related->getClassName().'::class';

        if ($this->needsForeignKey()) {
            $body .= ', '.Dumper::export($this->foreignKey());
        }

        if ($this->needsOtherKey()) {
            $body .= ', '.Dumper::export($this->otherKey());
        }

        $body .= ')';

        if ($this->hasCompositeOtherKey()) {
            // We will assume that when this happens the referenced columns are a composite primary key
            // or a composite unique key. Otherwise it should be a has-many relationship which is not
            // supported at the moment. @todo: Improve relationship resolution.
            foreach ($this->command->references as $index => $column) {
                $body .= "\n\t\t\t\t\t->where(".
                    Dumper::export($this->qualifiedOtherKey($index)).
                    ", '=', ".
                    Dumper::export($this->qualifiedForeignKey($index)).
                    ')';
            }
        }

        $body .= ';';

        return $body;
    }

    public function rGetMethod()
    {
        return Factory::transAttToMethod($this->name(), Factory::PREFIX_GET);
    }

    public function rBody($level = "\n\t\t")
    {
        $body[] = '/** @noinspection PhpUndefinedFieldInspection */';
        $body[] = 'return $this->'.str_replace('fk_', '', $this->foreignKey());
        return implode($level, $body);
    }

    public function getDoc($level = "\n\t")
    {
        $doc[] = '/**';
        $doc[] = ' * @return \Illuminate\Database\Eloquent\Relations\BelongsTo';
        $doc[] = '*/';
        return implode($level, $doc);
    }

    public function getRDoc($level = "\n\t")
    {
        $doc[] = '/**';
        $doc[] = ' * @return '.$this->getRelatedClass();
        $doc[] = '*/';
        return implode($level, $doc);
    }

    public function getFieldName()
    {
        return $this->foreignKey();
    }
    public function getAnnotation()
    {
        return $this->related->getClassName();
    }
    /**
     * @return string
     */
    public function hint()
    {
        return $this->related->getQualifiedUserClassName();
    }



    /**
     * @return bool
     */
    protected function needsForeignKey()
    {
        $defaultForeignKey = $this->related->getRecordName().'_id';

        return $defaultForeignKey != $this->foreignKey() || $this->needsOtherKey();
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function foreignKey($index = 0)
    {
        return $this->command->columns[$index];
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function qualifiedForeignKey($index = 0)
    {
        return $this->parent->getTable().'.'.$this->foreignKey($index);
    }

    /**
     * @return bool
     */
    protected function needsOtherKey()
    {
        $defaultOtherKey = $this->related->getPrimaryKey();

        return $defaultOtherKey != $this->otherKey();
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function otherKey($index = 0)
    {
        return $this->command->references[$index];
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function qualifiedOtherKey($index = 0)
    {
        return $this->related->getTable().'.'.$this->otherKey($index);
    }

    /**
     * Whether the "other key" is a composite foreign key.
     *
     * @return bool
     */
    protected function hasCompositeOtherKey()
    {
        return count($this->command->references) > 1;
    }

    /**
     * @return string
     */
    public function getRelatedClass()
    {
        return $this->related->getClassName();
    }


}
