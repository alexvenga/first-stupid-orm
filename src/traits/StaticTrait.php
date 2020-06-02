<?php


namespace AlexVenga\FirstStupidORM\Traits;


use AlexVenga\FirstStupidORM\ModelInterface;
use SafeMySQL;


trait StaticTrait
{

    /**
     * Current database connection.
     *
     * @var SafeMySQL
     */
    private static $connection;

    /**
     * Current table name.
     *
     * @var string
     */
    protected static $tableName;

    /**
     * Current table primary key.
     *
     * @var string
     */
    protected static $primaryKey;

    /**
     * Current table columns list.
     *
     * @var string[]
     */
    protected static $columnMap;

    /**
     * Current table relations list.
     *
     * @var ModelInterface
     */
    protected static $relations;

    /**
     * @return array
     */
    public static function getColumnMap()
    {
        return static::$columnMap;
    }


    /**
     * @param $connectionOptions
     * @return SafeMySQL
     */
    public static function connect($connectionOptions)
    {
        if (is_null(self::$connection)) {
            self::$connection = new SafeMySQL($connectionOptions);
        }

        return self::$connection;
    }

    /**
     * Get connection to database
     *
     * @return SafeMySQL
     */
    public static function getConnection()
    {
        return self::$connection;
    }

    /**
     * @param array|null $options
     * @return array
     */
    public static function findArray($options = null)
    {

        $sql = 'SELECT * FROM ?n';

        $params = [];
        if (isset($options['columns']) && is_array($options['columns']) && (!empty($options['columns']))) {
            $temp = [];
            foreach ($options['columns'] as $column) {
                $temp[] = '?n';
                // TODO Подумать както добавлять имя таблицы
                //$temp[] = '?n.?n';
                //$params[] = static::$tableName;
                $params[] = $column;
            }
            $sql = sprintf('SELECT %s FROM ?n', implode(', ', $temp));
        } elseif (isset($options['column']) && is_string($options['column']) && (!empty($options['column']))) {
            $params[] = $options['column'];
            $sql = 'SELECT ?n FROM ?n';
        }

        $params[] = static::$tableName;

        if (isset($options['where']) && is_string($options['where']) && (!empty($options['where']))) {
            $where = sprintf(' WHERE %s', $options['where']);
            if (isset($options['bind']) && is_array($options['bind']) && (!empty($options['bind']))) {
                foreach ($options['bind'] as $value) {
                    $params[] = $value;
                }
            }
            $sql .= $where;
        }

        if (isset($options['order']) && is_array($options['order']) && (!empty($options['order']))) {
            $temp = [];
            foreach ($options['order'] as $column => $type) {
                $temp[] = sprintf('?n %s', $type);
                $params[] = $column;
            }
            $sql .= sprintf(' ORDER BY %s', implode(', ', $temp));
        }

        if (isset($options['limit']) && is_scalar($options['limit'])) {
            $options['limit'] = (int)$options['limit'];
            if ($options['limit'] > 0) {
                $sql .= ' LIMIT ?i';
                $params[] = $options['limit'];
            }
        }

        if (isset($options['offset']) && is_scalar($options['offset'])) {
            $options['offset'] = (int)$options['offset'];
            if ($options['offset'] > 0) {
                $sql .= ' OFFSET ?i';
                $params[] = $options['offset'];
            }
        }

        if (isset($options['column']) && is_string($options['column']) && (!empty($options['column']))) {
            $rows = self::$connection->getCol($sql, ...$params);
        } else {
            $rows = self::$connection->getAll($sql, ...$params);
        }


        if (empty($rows)) {
            return $rows;
        }

        return $rows;

    }


    /**
     * @param array $rows
     * @param array|null $relationsOptions
     */
    public static function updateRelations(array &$rows, array $relationsOptions = null)
    {

        foreach ($relationsOptions as $relationOptions) {

            $currentRelation = static::$relations[$relationOptions['name']];

            $fieldIds = [];

            foreach ($rows as $key => $row) {
                $fieldIds[$row[$currentRelation['field']]] = $key;
            }

            asort($fieldIds);

            $queryOptions = [];
            $queryOptions['where'] = '?n IN (?a)';
            $queryOptions['bind'] = [
                $currentRelation['related_field'],
                array_keys($fieldIds),
            ];
            if (isset($relationOptions['relations'])) {
                $queryOptions['relations'] = $relationOptions['relations'];
            }
            if (isset($relationOptions['order'])) {
                $queryOptions['order'] = $relationOptions['order'];
            }
            if (isset($relationOptions['columns'])) {
                $queryOptions['columns'] = $relationOptions['columns'];
            }

            foreach ($rows as &$row) {
                $row['relations'][$relationOptions['name']] = [
                    'type' => null,
                    'data' => null,
                ];
            }

            $relatedRows = call_user_func_array($currentRelation['related_class'] . '::find', [$queryOptions]);

            if (empty($relatedRows)) {
                continue;
            }

            if (isset($relationOptions['limit'])) {
                $countRelatedToRow = $relationOptions['limit'];
            } else {
                $countRelatedToRow = PHP_INT_MAX;
            }

            $tempRelatedRows = [];

            foreach ($relatedRows as $relatedRow) {

                $relatedFieldMethod = sprintf('get%s', ucfirst(self::convertFieldToProperty($currentRelation['related_field'])));
                $relatedFieldValue = $relatedRow->$relatedFieldMethod();

                if ($currentRelation['type'] == 'many') {

                    if (!isset($tempRelatedRows[$relatedFieldValue])) {
                        $tempRelatedRows[$relatedFieldValue] = [];
                    }
                    if (count($tempRelatedRows[$relatedFieldValue]) < $countRelatedToRow) {
                        $tempRelatedRows[$relatedFieldValue][] = $relatedRow;
                    }

                } elseif ($currentRelation['type'] == 'one') {

                    $tempRelatedRows[$relatedFieldValue] = $relatedRow;

                }

            }

            $relatedRows = $tempRelatedRows;
            foreach ($rows as &$row) {
                if (isset($relatedRows[$row[$currentRelation['field']]])) {
                    $row['relations'][$relationOptions['name']] = [
                        'type' => $currentRelation['type'],
                        'data' => $relatedRows[$row[$currentRelation['field']]],
                    ];
                }
            }

        }

    }

    /**
     * @param array $options Array containing search params.
     * $options = [
     *  'columns' => [
     *          'column1',
     *          'column2',
     *      ],
     *  'where' => 'id > ?i',
     *  'bind' => [
     *          123,
     *      ],
     *  'order' => [
     *          'column' => 'DESC',
     *      ],
     *  'limit' => 0,
     *  'offset' => 12,
     *  'relations' => [
     *      [
     *          'name'  => 'relation_name',
     *          ... options like find ...
     *      ],
     * ]
     *
     * @return ModelInterface[]
     */
    public static function find($options = null): array
    {

        $rows = static::findArray($options);

        if (empty($rows)) {
            return $rows;
        }

        if (isset($options['relations']) && is_array($options['relations']) && (!empty($options['relations']))) {

            static::updateRelations($rows, $options['relations']);

        }

        foreach ($rows as &$row) {
            $row = new static($row);
        }

        return $rows;
    }

    /**
     * @param null $options
     * @return ModelInterface|null
     */
    public static function findFirst($options = null)
    {

        $options['limit'] = 1;

        $rows = static::find($options);

        if (empty($rows)) {
            return null;
        }

        return current($rows);
    }

    /**
     * @param string $url
     * @return ModelInterface|null
     */
    public static function findFirstByUrl($url = '')
    {

        return static::findFirst([
            'where' => 'url = ?s',
            'bind'  => [
                $url
            ],
        ]);
    }


}


