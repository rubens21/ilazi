<?php

/**
 * Created by Cristian.
 * Date: 19/09/16 11:58 PM.
 */

namespace ILazi\Coders\Model;

use Carbon\Carbon;
use Illuminate\Support\Str;
use ILazi\Coders\Model\Relations\BelongsTo;
use ILazi\Coders\Model\Relations\BelongsToMany;
use ILazi\Meta\Blueprint;
use ILazi\Support\Classify;
use ILazi\Meta\SchemaManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;

class Factory
{

    /**
     * Prefixed of the "get" magic methods
     */
    const PREFIX_GET = 'get';
    /**
     *Prefixed of the "set" magic methods
     */
    const PREFIX_SET = 'set';

    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    private $db;

    /**
     * @var \ILazi\Meta\SchemaManager
     */
    protected $schemas;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \ILazi\Support\Classify
     */
    protected $class;

    /**
     * @var \ILazi\Coders\Model\Config
     */
    protected $config;

    /**
     * @var \ILazi\Coders\Model\ModelManager
     */
    protected $models;

    /**
     * @var \ILazi\Coders\Model\Mutator[]
     */
    protected $mutators = [];

    private $ignoreAtt = [
        'id', 'created_at', 'updated_at'
    ];

    private $imports = [];

    /**
     * ModelsFactory constructor.
     *
     * @param \Illuminate\Database\DatabaseManager $db
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \ILazi\Support\Classify $writer
     * @param \ILazi\Coders\Model\Config $config
     */
    public function __construct(DatabaseManager $db, Filesystem $files, Classify $writer, Config $config)
    {
        $this->db = $db;
        $this->files = $files;
        $this->config = $config;
        $this->class = $writer;
    }

    /**
     * @return \ILazi\Coders\Model\Mutator
     */
    public function mutate()
    {
        return $this->mutators[] = new Mutator();
    }

    /**
     * @return \ILazi\Coders\Model\ModelManager
     */
    protected function models()
    {
        if (! isset($this->models)) {
            $this->models = new ModelManager($this);
        }

        return $this->models;
    }

    /**
     * Select connection to work with.
     *
     * @param string $connection
     *
     * @return $this
     */
    public function on($connection = null)
    {
        $this->schemas = new SchemaManager($this->db->connection($connection));

        return $this;
    }

    /**
     * @param string $schema
     */
    public function map($schema)
    {
        if (! isset($this->schemas)) {
            $this->on();
        }

        $mapper = $this->makeSchema($schema);

        foreach ($mapper->tables() as $blueprint) {
            if ($this->shouldNotExclude($blueprint)) {
                $this->create($mapper->schema(), $blueprint->table());
            }
        }
    }

    /**
     * @param \ILazi\Meta\Blueprint $blueprint
     *
     * @return bool
     */
    protected function shouldNotExclude(Blueprint $blueprint)
    {
        foreach ($this->config($blueprint, 'except', []) as $pattern) {
            if (Str::is($pattern, $blueprint->table())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $schema
     * @param string $table
     */
    public function create($schema, $table)
    {
        $model = $this->makeModel($schema, $table);
        $template = $this->prepareTemplate($model, 'model');

        $file = $this->fillTemplate($template, $model);

        $this->files->put($this->modelPath($model, $model->usesBaseFiles() ? ['Base'] : []), $file);

        if ($this->needsUserFile($model)) {
            $this->createUserFile($model);
        }
    }

    /**
     * @param string $schema
     * @param string $table
     *
     * @param bool $withRelations
     *
     * @return \ILazi\Coders\Model\Model
     */
    public function makeModel($schema, $table, $withRelations = true)
    {
        return $this->models()->make($schema, $table, $this->mutators, $withRelations);
    }

    /**
     * @param string $schema
     *
     * @return \ILazi\Meta\Schema
     */
    public function makeSchema($schema)
    {
        return $this->schemas->make($schema);
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     * @todo: Delegate workload to SchemaManager and ModelManager
     *
     * @return array
     */
    public function referencing(Model $model)
    {
        $references = [];

        // TODO: SchemaManager should do this
        foreach ($this->schemas as $schema) {
            $references = array_merge($references, $schema->referencing($model->getBlueprint()));
        }

        // TODO: ModelManager should do this
        foreach ($references as &$related) {
            $blueprint = $related['blueprint'];
            $related['model'] = $model->getBlueprint()->is($blueprint->schema(), $blueprint->table())
                ? $model
                : $this->makeModel($blueprint->schema(), $blueprint->table(), false);
        }

        return $references;
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     * @param string $name
     *
     * @return string
     */
    protected function prepareTemplate(Model $model, $name)
    {
        $defaultFile = $this->path([__DIR__, 'Templates', $name]);
        $file = $this->config($model->getBlueprint(), "*.template.$name", $defaultFile);

        return $this->files->get($file);
    }

    /**
     * @param string $template
     * @param \ILazi\Coders\Model\Model $model
     *
     * @return mixed
     */
    protected function fillTemplate($template, Model $model)
    {
        $template = str_replace('{{date}}', Carbon::now()->toRssString(), $template);
        $template = str_replace('{{namespace}}', $model->getNamespace(), $template);
        $template = str_replace('{{parent_full}}', $model->getParentClass(), $template);
        $template = str_replace('{{parent}}', basename(str_replace('\\', '/', $model->getParentClass())), $template);
        $template = str_replace('{{class}}', $model->getClassName(), $template);
        $template = str_replace('{{body}}', $this->body($model), $template);
        $template = str_replace('{{properties}}', $this->properties($model), $template);
        $template = str_replace('{{imports}}', $this->getImports($model), $template);

        return $template;
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     *
     * @return string
     */
    protected function properties(Model $model)
    {
        // Process property annotations
        $annotations = '';
        $relations = [];
        foreach ($model->getRelations() as $name => $relation) {
            // TODO: Handle collisions, perhaps rename the relation.
            if ($model->hasProperty($name)) {
                continue;
            }

//            $annotations .= $this->class->annotation('method',
//                $relation->getRelatedClass().' '.self::transAttToMethod($relation->getRelatedClass(),
//                    self::PREFIX_GET) . '()');
            if(in_array(get_class($relation), [BelongsTo::class, BelongsToMany::class])) {
                $relations[] = $relation->getFieldName();
            }

        }

        if ($model->hasRelations()) {
            // Add separation between model properties and model relations
            $annotations .= "\n * ";
        }

        $size = 10;
        foreach ($model->getProperties() as $name => $hint) {
            if(!in_array($name, $this->ignoreAtt) && !in_array($name, $relations)) {
                $import = explode('\\', $hint);
                if(count($import) > 1) {
                    $this->addImports($hint, $model);
                    $hint = array_pop($import);
                }
                $annotations .= $this->class->annotation('method', str_pad("\$this", $size, " ").' ' .self::transAttToMethod($name,
                        self::PREFIX_SET) . '(' . $hint . ' $' . $name . ') ');
                $annotations .= $this->class->annotation('method', str_pad($hint, $size, " ").' '.self::transAttToMethod($name,
                        self::PREFIX_GET, $hint) . '() ');
            }
        }



        return $annotations;
    }

    /**
     * Translate the name of the attribute to a method name
     *
     * @param $name
     * @param $prefix
     * @param bool $hint
     * @return string
     */
    public static function transAttToMethod($name, $prefix, $hint = false)
    {
        return ($hint === 'bool' && $prefix === self::PREFIX_GET ? 'is' : $prefix). studly_case($name);
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     *
     * @return string
     */
    protected function body(Model $model)
    {
        $body = '';

        foreach ($model->getTraits() as $trait) {
            $body .= $this->class->mixin($trait);
        }

        if ($model->hasCustomCreatedAtField()) {
            $body .= $this->class->constant('CREATED_AT', $model->getCreatedAtField());
        }

        if ($model->hasCustomUpdatedAtField()) {
            $body .= $this->class->constant('UPDATED_AT', $model->getUpdatedAtField());
        }

        if ($model->hasCustomDeletedAtField()) {
            $body .= $this->class->constant('DELETED_AT', $model->getDeletedAtField());
        }

        $body = trim($body, "\n");
        // Separate constants from fields only if there are constants.
        if (! empty($body)) {
            $body .= "\n";
        }

        // Append connection name when required
        if ($model->shouldShowConnection()) {
            $body .= $this->class->field('connection', $model->getConnectionName());
        }

        // When table is not plural, append the table name
        if ($model->needsTableName()) {
            $body .= $this->class->field('table', $model->getTableForQuery());
        }

        if ($model->hasCustomPrimaryKey()) {
            $body .= $this->class->field('primaryKey', $model->getPrimaryKey());
        }

        if ($model->doesNotAutoincrement()) {
            $body .= $this->class->field('incrementing', false, ['visibility' => 'public']);
        }

        if ($model->hasCustomPerPage()) {
            $body .= $this->class->field('perPage', $model->getPerPage());
        }

        if (! $model->usesTimestamps()) {
            $body .= $this->class->field('timestamps', false, ['visibility' => 'public']);
        }

        if ($model->hasCustomDateFormat()) {
            $body .= $this->class->field('dateFormat', $model->getDateFormat());
        }

        if ($model->doesNotUseSnakeAttributes()) {
            $body .= $this->class->field('snakeAttributes', false, ['visibility' => 'public static']);
        }

        if ($model->hasCasts()) {
            $body .= $this->class->field('casts', $model->getCasts(), ['before' => "\n"]);
        }

        if ($model->hasDates()) {
            $body .= $this->class->field('dates', $model->getDates(), ['before' => "\n"]);
        }

        if ($model->hasHidden() && $model->doesNotUseBaseFiles()) {
            $body .= $this->class->field('hidden', $model->getHidden(), ['before' => "\n"]);
        }

        if ($model->hasFillable() && $model->doesNotUseBaseFiles()) {
            $body .= $this->class->field('fillable', $model->getFillable(), ['before' => "\n"]);
        }

        if ($model->hasHints() && $model->usesHints()) {
            $body .= $this->class->field('hints', $model->getHints(), ['before' => "\n"]);
        }

        foreach ($model->getMutations() as $mutation) {
            $body .= $this->class->method($mutation->name(), $mutation->body(), ['before' => "\n"]);
        }

        foreach ($model->getRelations() as $constraint) {
            $body .= "\n\n\t".$constraint->getDoc();
            $body .= $this->class->method($constraint->name(), $constraint->body());

            $body .= "\n\n\t".$constraint->getRDoc();
            $body .= $this->class->method($constraint->rGetMethod(), $constraint->rBody());

            if(in_array(get_class($constraint), [BelongsTo::class, BelongsToMany::class])) {
                $this->addImports($constraint->hint(), $model);
            }

        }

        // Make sure there not undesired line breaks
        $body = trim($body, "\n");

        return $body;
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     *
     * @param array $custom
     *
     * @return string
     */
    protected function modelPath(Model $model, $custom = [])
    {
        $modelsDirectory = $this->path(array_merge([$this->config($model->getBlueprint(), 'path')], $custom));

        if (! $this->files->isDirectory($modelsDirectory)) {
            $this->files->makeDirectory($modelsDirectory, 0755, true);
        }

        return $this->path([$modelsDirectory, $model->getClassName().'.php']);
    }

    /**
     * @param array $pieces
     *
     * @return string
     */
    protected function path($pieces)
    {
        return implode(DIRECTORY_SEPARATOR, (array) $pieces);
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     *
     * @return bool
     */
    public function needsUserFile(Model $model)
    {
        return ! $this->files->exists($this->modelPath($model)) && $model->usesBaseFiles();
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     */
    protected function createUserFile(Model $model)
    {
        $file = $this->modelPath($model);

        $template = $this->prepareTemplate($model, 'user_model');
        $template = str_replace('{{namespace}}', $model->getNamespace(), $template);
        $template = str_replace('{{class}}', $model->getClassName(), $template);
        $template = str_replace('{{parent}}', '\\'.$model->getBaseNamespace().'\\'.$model->getClassName(), $template);
        $template = str_replace('{{body}}', $this->userFileBody($model), $template);

        $this->files->put($file, $template);
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     *
     * @return string
     */
    protected function userFileBody(Model $model)
    {
        $body = '';

        if ($model->hasHidden()) {
            $body .= $this->class->field('hidden', $model->getHidden());
        }

        if ($model->hasFillable()) {
            $body .= $this->class->field('fillable', $model->getFillable(), ['before' => "\n"]);
        }

        // Make sure there is not an undesired line break at the end of the class body
        $body = ltrim(rtrim($body, "\n"), "\n");

        return $body;
    }

    /**
     * @param \ILazi\Meta\Blueprint|null $blueprint
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|\ILazi\Coders\Model\Config
     */
    public function config(Blueprint $blueprint = null, $key = null, $default = null)
    {
        if (is_null($blueprint)) {
            return $this->config;
        }

        return $this->config->get($blueprint, $key, $default);
    }

    /**
     * @param \ILazi\Coders\Model\Model $model
     * @return string
     */
    private function getImports(Model $model)
    {
        return isset($this->imports[$model->getClassName()]) ? implode(";\n", $this->imports[$model->getClassName()]).';' : '';
    }

    protected function addImports($className, Model $model)
    {
        if($className !== $model->getNamespace()) {
            $this->imports[$model->getClassName()][] = 'use '.$className;
        }
    }

}
