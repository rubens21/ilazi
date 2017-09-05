<?php

/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace ILazi\Coders\Model\Relations;

use Illuminate\Support\Fluent;
use ILazi\Coders\Model\Factory;
use ILazi\Coders\Model\Model;
use ILazi\Coders\Model\Relation;

class HasOneOrManyStrategy implements Relation
{
    /**
     * @var \ILazi\Coders\Model\Relation
     */
    protected $relation;

    /**
     * HasManyWriter constructor.
     *
     * @param \Illuminate\Support\Fluent $command
     * @param \ILazi\Coders\Model\Model $parent
     * @param \ILazi\Coders\Model\Model $related
     */
    public function __construct(Fluent $command, Model $parent, Model $related)
    {
        if (
            $related->isPrimaryKey($command) ||
            $related->isUniqueKey($command)
        ) {
            $this->relation = new HasOne($command, $parent, $related);
        } else {
            $this->relation = new HasMany($command, $parent, $related);
        }
    }

    /**
     * @return string
     */
    public function hint()
    {
        return $this->relation->hint();
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->relation->name();
    }

    /**
     * @return string
     */
    public function body()
    {
        return $this->relation->body();
    }


    /**
     * @return string
     */
    public function getRelatedClass()
    {
        return $this->relation->getRelatedClass();
    }

    public function rBody($level = "\n\t\t")
    {
        $body[] = '/** @noinspection PhpIncompatibleReturnTypeInspection */';
        $body[] = 'return $this->'.$this->name().'->get();';
        return implode($level, $body);
    }

    public function rGetMethod()
    {
        return Factory::transAttToMethod($this->name(), Factory::PREFIX_GET);
    }
    public function getRDoc($level = "\n\t")
    {
        $doc[] = '/**';
        $doc[] = ' * @return '.$this->getRelatedClass().'[]';
        $doc[] = '*/';
        return implode($level, $doc);
    }

    public function getDoc($level = "\n\t")
    {
        $doc[] = '/**';
        $doc[] = ' * @return \Illuminate\Database\Eloquent\Relations\hasMany';
        $doc[] = '*/';
        return implode($level, $doc);
    }
}
