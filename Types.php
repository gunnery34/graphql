<?php

require_once 'vendor/autoload.php';

use GraphQL\Type\Definition\Type;

/**
 * Class Types
 * 
 * Class digunakan untuk mengambil instance
 * dari type yang tersedia pada graphql yang kita buat
 * 
 * Instance yang diambil dipakai untuk mendefinisikan type ke setiap node pada graphql
 *
 */
class Types extends Type
{

    protected static $typeInstances = [];

    public static function user()
    {
        return static::getInstance(UserType::class);
    }

    public static function product()
    {
        return static::getInstance(ProductType::class);
    }

    public static function productCategory()
    {
        return static::getInstance(ProductCategoryType::class);
    }

    protected static function getInstance($class, $arg = null)
    {
        if (!isset(static::$typeInstances[$class])) {
            $type = new $class($arg);
            static::$typeInstances[$class] = $type;
        }

        return static::$typeInstances[$class];
    }
}
