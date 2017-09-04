<?php

/**
 * Created by Cristian.
 * Date: 05/09/16 11:27 PM.
 */

namespace Reliese\Coders\Model;

interface Relation
{
    /**
     * @return string
     */
    public function hint();

    /**
     * @return string
     */
    public function getRelatedClass();

    /**
     * @return string
     */
    public function name();

    /**
     * @return string
     */
    public function body();
    public function rBody();
    public function rGetMethod();
    public function getRDoc();

    public function getDoc();
}
