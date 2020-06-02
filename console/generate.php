#!/usr/bin/env php
<?php


/////////////////////////////////////////////////////////
/// Config //////////////////////////////////////////////
/////////////////////////////////////////////////////////

$options = [
    'db'                  => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'pass' => 'root',
        'db'   => 'population'
    ],
    'disable_temp'        => true,
    'namespace'           => 'AlexVenga\Population\Models',
    'create_final_models' => false,
    'files_path'          => '/Volumes/SecondHDD/Users/ALEX/Work/MyLibraries/first-stupid-orm/test',
];


/////////////////////////////////////////////////////////
/// Initialization //////////////////////////////////////
/////////////////////////////////////////////////////////


require_once '../vendor/autoload.php';


use Codedungeon\PHPCliColors\Color;


$defaultConnection = new SafeMySQL($options['db']);


/////////////////////////////////////////////////////////
/// Main ////////////////////////////////////////////////
/////////////////////////////////////////////////////////


$tablesList = $defaultConnection->getAll('SHOW TABLES');

echo sprintf("Finded %s%s%s tables\n", Color::GREEN, count($tablesList), Color::RESET);

$tables = [];
$tablesColumns = [];
$tablesPrimaryKeys = [];
$tablesRelations = [];
foreach ($tablesList as $table) {
    $tableName = current($table);
    if ($options['disable_temp'] && (mb_strpos($tableName, 'temp') === 0)) {
        continue;
    }
    $tableModelName = ucfirst(convertFieldToProperty($tableName));
    $tables[$tableName] = $tableModelName;
    $tablesColumns[$tableName] = [];
    $tablesPrimaryKeys[$tableName] = [];
}
echo sprintf("Loaded %s%s%s tables\n", Color::BLUE, count($tables), Color::RESET);

$countFields = 0;
foreach ($tables as $tableName => $tableModelName) {
    $rows = $defaultConnection->getAll('SHOW COLUMNS FROM ?n', $tableName);
    foreach ($rows as $row) {
        $tablesColumns[$tableName][$row['Field']] = [];
        $tablesColumns[$tableName][$row['Field']]['type'] = 'string';
        if (mb_strpos($row['Type'], 'int(') !== false) {
            $tablesColumns[$tableName][$row['Field']]['type'] = 'int';
        }
        $tablesColumns[$tableName][$row['Field']]['type_string'] = $row['Type'];
        $tablesColumns[$tableName][$row['Field']]['null'] = 'NOT NULL';
        if ($row['Null'] == 'YES') {
            $tablesColumns[$tableName][$row['Field']]['null'] = 'NULL';
        }
        $tablesColumns[$tableName][$row['Field']]['primary_key'] = 0;
        if ($row['Key'] == 'PRI') {
            $tablesColumns[$tableName][$row['Field']]['primary_key'] = 1;
        }
        $tablesColumns[$tableName][$row['Field']]['unique'] = 0;
        if ($row['Key'] == 'UNI') {
            $tablesColumns[$tableName][$row['Field']]['unique'] = 1;
        }
        $tablesColumns[$tableName][$row['Field']]['primary'] = 0;
        if ($row['Key'] == 'PRI') {
            $tablesColumns[$tableName][$row['Field']]['primary'] = 1;
        }
        if ($row['Key'] == 'PRI') {
            $tablesPrimaryKeys[$tableName][] = $row['Field'];
        }
        $tablesColumns[$tableName][$row['Field']]['default'] = $row['Default'];
        $tablesColumns[$tableName][$row['Field']]['extra'] = $row['Extra'];
        $countFields++;
    }

    if (count($tablesPrimaryKeys[$tableName]) == 1) {
        $tablesPrimaryKeys[$tableName] = sprintf('\'%s\'', current($tablesPrimaryKeys[$tableName]));
    } else {
        foreach ($tablesPrimaryKeys[$tableName] as &$key) {
            $key = sprintf('\'%s\'', $key);
        }
        $tablesPrimaryKeys[$tableName] = sprintf('[%s]', implode(', ', $tablesPrimaryKeys[$tableName]));
    }

    $tablesRelations[$tableName] = [];

}
echo sprintf("Loaded %s%s%s fields\n", Color::PURPLE, $countFields, Color::RESET);


$createdFiles = [];
foreach ($tables as $tableName => $tableModelName) {

    $tableModelRowsFileName = sprintf('%s/%sFieldsModel.php', $options['files_path'], $tableModelName);
    $tableModelRowsFile = fopen($tableModelRowsFileName, 'w');

    fwrite($tableModelRowsFile, "<?php\n\n\n");

    fwrite($tableModelRowsFile, sprintf("namespace %s;\n\n\n", $options['namespace']));

    fwrite($tableModelRowsFile, sprintf("class %sFieldsModel extends BaseModel\n", $tableModelName));

    fwrite($tableModelRowsFile, "{\n\n");

    fwrite($tableModelRowsFile, sprintf('    protected static $tableName = \'%s\';%s', $tableName, "\n\n"));

    fwrite($tableModelRowsFile, sprintf('    protected static $primaryKey = %s;%s', $tablesPrimaryKeys[$tableName], "\n\n"));

    fwrite($tableModelRowsFile, sprintf('    protected static $columnMap = [%s', "\n"));

    foreach ($tablesColumns[$tableName] as $columnName => $columnData) {
        fwrite($tableModelRowsFile, sprintf('        \'%s\',%s', $columnName, "\n"));
    }

    fwrite($tableModelRowsFile, sprintf('    ];%s', "\n\n\n"));

    foreach ($tablesColumns[$tableName] as $columnName => $columnData) {
        fwrite($tableModelRowsFile, sprintf('    /**%s', "\n"));
        fwrite($tableModelRowsFile, '     *');
        fwrite($tableModelRowsFile, sprintf(' %s %s %s', $columnData['type_string'], $columnData['null'], "\n"));
        if ($columnData['primary_key']) {
            fwrite($tableModelRowsFile, sprintf('     * PRIMARY%s', "\n"));
        }
        if ($columnData['unique']) {
            fwrite($tableModelRowsFile, sprintf('     * UNIQUE%s', "\n"));
        }
        if ($columnData['default']) {
            fwrite($tableModelRowsFile, sprintf('     * DEFAULT: %s%s', $columnData['default'], "\n"));
        }
        if ($columnData['extra']) {
            fwrite($tableModelRowsFile, sprintf('     * %s%s', $columnData['extra'], "\n"));
        }

        fwrite($tableModelRowsFile, sprintf('     *%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('     * @var %s%s', $columnData['type'], "\n"));
        fwrite($tableModelRowsFile, sprintf('     */%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('    protected $%s;%s', convertFieldToProperty($columnName), "\n\n"));
    }

    fwrite($tableModelRowsFile, "\n");


    foreach ($tablesColumns[$tableName] as $columnName => $columnData) {

        fwrite($tableModelRowsFile, sprintf('    /**%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('     * @return %s%s', $columnData['type'], "\n"));
        fwrite($tableModelRowsFile, sprintf('     */%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('    public function get%s(): %s%s',
            ucfirst(convertFieldToProperty($columnName)),
            $columnData['type'],
            "\n"));
        fwrite($tableModelRowsFile, sprintf('    {%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('        return $this->%s;%s', convertFieldToProperty($columnName), "\n"));
        fwrite($tableModelRowsFile, sprintf('    }%s', "\n\n"));

        /////

        fwrite($tableModelRowsFile, sprintf('    /**%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('     * @param %s $%s%s', $columnData['type'], convertFieldToProperty($columnName), "\n"));
        fwrite($tableModelRowsFile, sprintf('     * @return %sModel%s', $tableModelName, "\n"));
        fwrite($tableModelRowsFile, sprintf('     */%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('    public function set%s(%s $%s): %sModel%s',
            ucfirst(convertFieldToProperty($columnName)),
            $columnData['type'],
            convertFieldToProperty($columnName),
            $tableModelName,
            "\n"));
        fwrite($tableModelRowsFile, sprintf('    {%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('        $this->%s = $%s;%s', convertFieldToProperty($columnName), convertFieldToProperty($columnName), "\n"));
        fwrite($tableModelRowsFile, sprintf('        return $this;%s', "\n"));
        fwrite($tableModelRowsFile, sprintf('    }%s', "\n\n"));

    }

    fwrite($tableModelRowsFile, "\n}");

    fclose($tableModelRowsFile);

    $createdFiles[] = sprintf('%sFieldsModel.php', $tableModelName);
}

echo sprintf("\nCreated files:\n%s/%s%s\n", Color::YELLOW, implode("\n/", $createdFiles), Color::RESET);

$sql = 'SELECT' .
    '`TABLE_NAME`, ' .
    '`COLUMN_NAME`, ' .
    '`REFERENCED_TABLE_NAME`, ' .
    '`REFERENCED_COLUMN_NAME` ' .
    'FROM ' .
    '`INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` ' .
    'WHERE ' .
    '`TABLE_SCHEMA` = ?s ' .
    'AND `REFERENCED_TABLE_NAME` IS NOT NULL';
$rows = $defaultConnection->getAll($sql, $options['db']['db']);
$countRelations = 0;
foreach ($rows as $row) {
    $tablesRelations[$row['TABLE_NAME']][$row['REFERENCED_TABLE_NAME']] = [
        'type'          => 'one',
        'field'         => $row['COLUMN_NAME'],
        'related_class' => sprintf('%s\%sModel', $options['namespace'], ucfirst(convertFieldToProperty($row['REFERENCED_TABLE_NAME']))),
        'related_field' => $row['REFERENCED_COLUMN_NAME'],
    ];
    $type = 'many';
    if (count(explode(',', $tablesPrimaryKeys[$row['TABLE_NAME']])) == 1) {
        $temp = trim($tablesPrimaryKeys[$row['TABLE_NAME']]);
        $temp = trim($temp, '\'');
        if ($row['COLUMN_NAME'] == $temp) {
            $type = 'one';
        }
    }
    $tablesRelations[$row['REFERENCED_TABLE_NAME']][$row['TABLE_NAME']] = [
        'type'          => $type,
        'field'         => $row['REFERENCED_COLUMN_NAME'],
        'related_class' => sprintf('%s\%sModel', $options['namespace'], ucfirst(convertFieldToProperty($row['TABLE_NAME']))),
        'related_field' => $row['COLUMN_NAME'],
    ];
    $countRelations += 2;
}
echo sprintf("\nLoaded %s%s%s relations\n", Color::GREEN, $countRelations, Color::RESET);

$createdFiles = [];
foreach ($tables as $tableName => $tableModelName) {

    $tableModelRowsFileName = sprintf('%s/%sRelationsModel.php', $options['files_path'], $tableModelName);
    $tableModelRowsFile = fopen($tableModelRowsFileName, 'w');

    fwrite($tableModelRowsFile, "<?php\n\n\n");

    fwrite($tableModelRowsFile, sprintf("namespace %s;\n\n\n", $options['namespace']));

    fwrite($tableModelRowsFile, sprintf("class %sRelationsModel extends %sFieldsModel\n", $tableModelName, $tableModelName));

    fwrite($tableModelRowsFile, "{\n\n");

    if (!empty($tablesRelations[$tableName])) {

        fwrite($tableModelRowsFile, sprintf('    protected static $relations = [%s', "\n\n"));
        foreach ($tablesRelations[$tableName] as $relationName => $relationData) {
            fwrite($tableModelRowsFile, sprintf('        \'%s\' => [%s', $relationName, "\n"));
            foreach ($relationData as $key => $value) {
                fwrite($tableModelRowsFile, sprintf('            \'%s\' => \'%s\',%s', $key, $value, "\n"));
            }
            fwrite($tableModelRowsFile, sprintf('        ],%s', "\n\n"));
        }
        fwrite($tableModelRowsFile, sprintf('    ];%s', "\n\n\n"));

        foreach ($tablesRelations[$tableName] as $relationName => $relationData) {
            fwrite($tableModelRowsFile, sprintf('    /**%s', "\n"));
            fwrite($tableModelRowsFile, sprintf('     * @return %s%s%s',
                trim(str_replace($options['namespace'], '', $relationData['related_class']), '\\'),
                ($relationData['type'] == 'many') ? '[]|null' : '',
                "\n"));
            fwrite($tableModelRowsFile, sprintf('     */%s', "\n"));

            fwrite($tableModelRowsFile, sprintf('    public function getRelated%s()%s',
                    ucfirst(convertFieldToProperty($relationName)),
                    "\n")
            );

            //fwrite($tableModelRowsFile, sprintf('    public function getRelated%s(): %s%s',
            //        ucfirst(convertFieldToProperty($relationName)),
            //        ($relationData['type'] == 'many') ? 'array' : ucfirst(convertFieldToProperty($relationName)) . 'Model',
            //        "\n")
            //);

            fwrite($tableModelRowsFile, sprintf('    {%s', "\n"));
            fwrite($tableModelRowsFile, sprintf('        if (!$this->isRelationLoaded(\'%s\')) {%s',
                    $relationName,
                    "\n")
            );
            fwrite($tableModelRowsFile, sprintf('            $this->updateRelation(\'%s\');%s',
                    $relationName,
                    "\n")
            );
            fwrite($tableModelRowsFile, sprintf('        }%s', "\n\n"));
            fwrite($tableModelRowsFile, sprintf('        return $this->relatedData[\'%s\'][\'data\'];%s',
                    $relationName,
                    "\n")
            );
            fwrite($tableModelRowsFile, sprintf('    }%s', "\n\n"));
        }

    }

    fwrite($tableModelRowsFile, "\n}\n");

    fclose($tableModelRowsFile);

    $createdFiles[] = sprintf('/%sRelationsModel.php', $tableModelName);
}

echo sprintf("\nCreated files:\n%s/%s%s\n", Color::YELLOW, implode("\n/", $createdFiles), Color::RESET);


if ($options['create_final_models']) {
    $createdFiles = [];
    foreach ($tables as $tableName => $tableModelName) {

        $tableModelRowsFileName = sprintf('%s/%sModel.php', $options['files_path'], $tableModelName);
        $tableModelRowsFile = fopen($tableModelRowsFileName, 'w');

        fwrite($tableModelRowsFile, "<?php\n\n\n");

        fwrite($tableModelRowsFile, sprintf("namespace %s;\n\n\n", $options['namespace']));

        fwrite($tableModelRowsFile, sprintf("class %sModel extends %sRelationsModel\n", $tableModelName, $tableModelName));

        fwrite($tableModelRowsFile, "{\n\n");

        //fwrite($tableModelRowsFile, sprintf('    /**%s', "\n"));
        //fwrite($tableModelRowsFile, sprintf('     * @return string%s', "\n"));
        //fwrite($tableModelRowsFile, sprintf('     */%s', "\n"));
        //fwrite($tableModelRowsFile, sprintf('    public function getSlug(): string%s', "\n")
        //);
        //fwrite($tableModelRowsFile, sprintf('    {%s', "\n"));
        //fwrite($tableModelRowsFile, sprintf('        return \'\';%s', "\n"));
        //fwrite($tableModelRowsFile, sprintf('    }%s', "\n\n"));

        fwrite($tableModelRowsFile, "\n}");

        fclose($tableModelRowsFile);

        $createdFiles[] = sprintf(' %sModel.php', $tableModelName);
    }

    echo sprintf("\nCreated files:\n%s/%s%s\n", Color::YELLOW, implode("\n/", $createdFiles), Color::RESET);
}

echo PHP_EOL;


//


/////////////////////////////////////////////////////////
/// Functions ///////////////////////////////////////////
/////////////////////////////////////////////////////////


function convertFieldToProperty(string $fieldName = ''): string
{
    if (mb_strpos($fieldName, '_') === false) {
        return $fieldName;
    }

    $fieldName = str_replace('_', ' ', $fieldName);
    $fieldName = trim($fieldName);
    $fieldName = ucwords($fieldName);
    $fieldName = str_replace(' ', '', $fieldName);
    $fieldName = lcfirst($fieldName);

    return $fieldName;
}

