<?php
/**
 * Created by IntelliJ IDEA.
 * User: Rubens
 * Date: 2017-01-22
 * Time: 9:35 AM
 */

namespace ILazi\Coders\Model;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseModel
 * @package App\Model
 * @property int $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @mixin Builder
 * @method static $this find($id, $columns = ['*'])
 * @method static $this findOrFail($id, $columns = ['*'])
 * @method static $this first($columns = ['*'])
 * @method static $this|$this[] get($columns = ['*'])
 * @method static $this|$this[] where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this|$this[] orWhere($column, $operator = null, $value = null)
 * @method static $this|$this[] with($relations)
 * @method static $this getQuery()
 */
class BaseModel extends Model
{
    /**
     * @var string[] Keep the methods in memory to make faster reading to find the methods name
     */
    private static $docMagicMethod;

    /**
     * Prefixed of the "get" magic methods
     */
    const PREFIX_GET = 'get';
    /**
     *Prefixed of the "set" magic methods
     */
    const PREFIX_SET = 'set';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * @inheritdoc
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        /** @var BaseModel $obj */
        $obj = parent::newFromBuilder($attributes, $connection);
        return $obj->internalConstruct();
    }

    /**
     * Overwrite this method in your model if you want to run something after Eloquent has created a new object
     *
     * @return $this
     */
    protected function internalConstruct()
    {
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Carbon
     */
    public function getCreatedAt(): Carbon
    {
        return $this->created_at;
    }

    /**
     * @return Carbon
     */
    public function getUpdatedAt(): Carbon
    {
        return $this->updated_at;
    }

    //region Methods to improve development speed

    /**
     * It handle the methods "magic" to identify get/set
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, $this->getAttMethodName(self::PREFIX_GET))) {
            return $this->genericGet($method);
        } elseif (in_array($method, $this->getAttMethodName(self::PREFIX_SET))) {
            return $this->genericSet($method, $parameters[0]);
        } else {
            return parent::__call($method, $parameters);
        }
    }

    /**
     * Handle the GET method
     *
     * @param $method
     * @return mixed
     */
    protected function genericGet($method)
    {
        return $this->{self::transMethodToAtt($method, self::PREFIX_GET)};
    }

    /**
     * Handle the SET method
     *
     * @param $method
     * @param $val
     * @return mixed
     */
    protected function genericSet($method, $val)
    {
        $attribute = self::transMethodToAtt($method, self::PREFIX_GET);
        $this->{$attribute} = $val;
        return $this->{$attribute};
    }

    /**
     * Translate the name of the method to a attribute name
     *
     * @param $method
     * @param $prefix
     * @return string
     */
    private static function transMethodToAtt($method, $prefix)
    {
        return snake_case(substr($method, strlen($prefix)));
    }

    /**
     * Translate the name of the attribute to a method name
     *
     * @param $name
     * @param $prefix
     * @return string
     */
    private static function transAttToMethod($name, $prefix)
    {
        return $prefix . studly_case($name);
    }

    /**
     * Get the methods get ou set of the class
     *
     * @param $prefix
     * @return array
     */
    protected function getAttMethodName($prefix)
    {
        return $this->getDocMethods()[$prefix];
    }

    public function developerHelper()
    {
        $tableColInfo = \DB::select(\DB::raw('SHOW COLUMNS FROM ' . $this->getTable() . ''));
        $fields = [];
        foreach ($tableColInfo as $column) {
            $fields[$column->Field] = $column->Type;
        }
        $attributes = $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
        $doc[] = '/**';
        $dates = [];
        foreach ($attributes as $name) {
            $type = $this->transCastMysqlToPhp($fields[$name]);
            $doc[] = ' * @method ' . $type . '  ' . self::transAttToMethod($name, self::PREFIX_GET) . '() ';
            $doc[] = ' * @method $this ' . self::transAttToMethod($name,
                    self::PREFIX_SET) . '(' . $type . ' $' . $name . ') ';//'.$this->translateCastMysqlToPhp($fields[$name]).'
            if ($type == 'Carbon') {
                $dates[] = $name;
            }
        }
        $doc[] = '*/';
        if ($dates) {
            $doc[] = '/**';
            $doc[] = ' * The attributes that should be mutated to dates.';
            $doc[] = ' *';
            $doc[] = ' * @var array';
            $doc[] = ' */';
            $doc[] = 'protected $dates = [\'' . implode("', '", $dates) . '\'];';
        }
        return implode("\n", $doc);
    }

    private function transCastMysqlToPhp($type)
    {
        if (strpos($type, '(') !== false) {
            $type = substr(strtolower($type), 0, strpos($type, '('));
        }
        switch ($type) {
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
            case 'float':
            case 'double':
            case 'decimal':
            case 'year':
                return 'int';
            case 'bit':
            case 'tinyint':
                return 'bool';
            case 'char':
            case 'varchar':
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'binary':
            case 'varbinary':
            case 'tinyblob':
            case 'blob':
            case 'mediumblob':
            case 'longblob':
            case 'enum':
                return 'string';
            case 'date':
            case 'datetime':
            case 'time':
            case 'timestamp':
                return 'Carbon';
            default:
                return $type;
        }
    }

    private static function getDocMethods()
    {
        if (!isset(self::$docMagicMethod) || !isset(self::$docMagicMethod[get_called_class()])) {
            $methods = [];
            //you have to invert to subscribe the methods of the parent class
            $parents = array_reverse(class_parents(get_called_class()));
            foreach (array_slice($parents, 2) as $class) {
                $methods = array_merge($methods, self::listMethodFromClass($class));
            }
            $methods = array_merge($methods, self::listMethodFromClass(get_called_class()));

            self::$docMagicMethod[get_called_class()] = $methods;
            if (!isset(self::$docMagicMethod[get_called_class()][self::PREFIX_GET])) {
                self::$docMagicMethod[get_called_class()][self::PREFIX_GET] = [];
            }
            if (!isset(self::$docMagicMethod[get_called_class()][self::PREFIX_SET])) {
                self::$docMagicMethod[get_called_class()][self::PREFIX_SET] = [];
            }
        }
        return self::$docMagicMethod[get_called_class()];
    }

    private static function listMethodFromClass($class)
    {
        $methods = [];
        $ref = new \ReflectionClass($class);
        $regx = '\*\s+@method';//PHP doc
        $regx .= '\s+[^\s]+\s+';//tipo retornado pelo método
        $regx .= '(?<method>(?<type>set|get)[^(]+)';//nome do método (tudo antes do parêntese
        $regx .= '\s*\([^(]*\)';//parenteses com ou sem valores entre eles (e pode ter um espaço em brando antes
        preg_match_all('/' . $regx . '/', $ref->getDocComment(), $matches);
        foreach ($matches['method'] as $pos => $methodName) {
            $methods[$matches['type'][$pos]][] = $methodName;
        }
        return $methods;
    }

    //endregion
}