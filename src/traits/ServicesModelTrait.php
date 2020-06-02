<?php


namespace AlexVenga\FirstStupidORM\Traits;


trait ServicesModelTrait
{

    /**
     * Conver database field name to property name "company_name" => "companyName", "companyName" => "companyName"
     * @param string $fieldName
     * @return string
     */
    public static function convertFieldToProperty($fieldName = '')
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

}