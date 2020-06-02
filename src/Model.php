<?php


namespace AlexVenga\FirstStupidORM;


use AlexVenga\FirstStupidORM\Traits\ServicesModelTrait;
use AlexVenga\FirstStupidORM\Traits\ShowModelContentTrait;
use AlexVenga\FirstStupidORM\Traits\StaticTrait;


class Model implements ModelInterface
{
    use ServicesModelTrait;
    use StaticTrait;
    use ShowModelContentTrait;

    /**
     * Current table related tables data.
     *
     * @var array
     */
    protected $relatedData;

    /**
     * Other fields/counters data.
     *
     * @var array
     */
    protected $otherData = [];

    /**
     * Model constructor.
     * @param array $data
     */
    public function __construct($data = [])
    {

        if (empty($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $propertyName = self::convertFieldToProperty($key);
                $this->$propertyName = $value;
            }
        }

        if (isset($data['relations'])) {
            $this->relatedData = $data['relations'];
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->otherData[$name] = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->otherData[$name])) {
            return $this->otherData[$name];
        }

        return null;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __isset($name)
    {
        if (isset($this->otherData[$name])) {
            return true;
        }

        return false;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __unset($name)
    {
        if (isset($this->otherData[$name])) {
            unset($this->otherData[$name]);
        }

        return null;
    }

    /**
     * @param string $relationName
     */
    public function updateRelation($relationName = '')
    {

        $fieldName = 'get' . static::convertFieldToProperty(static::$relations[$relationName]['field']);

        $findOptions = [
            'where' => sprintf('%s = ?s', static::$relations[$relationName]['related_field']),
            'bind'  => [
                $this->$fieldName(),
            ],
        ];

        if (static::$relations[$relationName]['type'] == 'many') {
            $data = static::$relations[$relationName]['related_class']::find($findOptions);
            $this->relatedData[$relationName] = [
                'type' => 'many',
                'data' => $data,
            ];
        } else {
            $data = static::$relations[$relationName]['related_class']::findFirst($findOptions);
            $this->relatedData[$relationName] = [
                'type' => 'one',
                'data' => $data,
            ];
        }
    }


    /**
     * @param string $relationName
     * @return bool
     */
    public function isRelationLoaded($relationName = '')
    {

        if (!isset($this->relatedData[$relationName])) {
            return false;
        }

        return true;
    }

}