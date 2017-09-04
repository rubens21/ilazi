<?php

/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace Reliese\Coders\Model\Relations;

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relation;

class HasOneOrManyStrategy implements Relation
{
    /**
     * @var \Reliese\Coders\Model\Relation
     */
    protected $relation;

    /**
     * HasManyWriter constructor.
     *
     * @param \Illuminate\Support\Fluent $command
     * @param \Reliese\Coders\Model\Model $parent
     * @param \Reliese\Coders\Model\Model $related
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

    public function getDoc()
    {
        // TODO: Implement getDoc() method.
    }

    /**
     * @return string
     */
    public function getRelatedClass()
    {
        return $this->relation->getRelatedClass();
    }

    public function rBody()
    {
        // TODO: Implement bodyR() method.
    }

    public function rGetMethod()
    {
        // TODO: Implement rGetMethod() method.
    }
    public function getRDoc()
    {
        // TODO: Implement getRDoc() method.
    }
}
