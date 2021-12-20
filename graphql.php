<?php

require_once 'vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Error\FormattedError;
use GraphQL\Type\Definition\ObjectType;


define('BASE_URL', 'http://localhost:8080');

ini_set('display_errors', 0);

$debug = !empty($_GET['debug']);
if ($debug) {
    $phpErrors = [];
    set_error_handler(function($severity, $message, $file, $line) use (&$phpErrors) {
        $phpErrors[] = new ErrorException($message, 0, $severity, $file, $line);
    });
}

try {
    // ## Koneksi PDO 
    // -----------------------------------------------------------------------------------------------
    $dbHost     = "localhost";
    $dbUsername = "root";
    $dbPassword = "";
    $dbName     = "graphql_php";
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUsername, $dbPassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // app context akan dikirimkan oleh library graphql 
    $appContext = [
        'user_id' => null, 
        'pdo' => $pdo // untuk nantinya dipakai dalam melakukan query select
    ];

    // Mengambil data yang dikirimkan client
    // -----------------------------------------------------------------------------------------------

    // jika request header content_type adalah 'application/json' ...
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
    } else {
        $data = $_REQUEST;
    }
    // merge data (memastikan supaya tidak terjadi error undefined index array)
    $data += ['query' => null, 'variables' => null];
    if (null === $data['query']) {
        $data['query'] = '{hello}';
    }

    
    // Load Types
    // -----------------------------------------------------------------------------------------------
    // Load beberapa type pada file lain
    require __DIR__ . '/Types.php';
    require __DIR__ . '/types/UserType.php';
    require __DIR__ . '/types/ProductType.php';
    require __DIR__ . '/types/ProductCategoryType.php';
   

    // Query Type
    // -----------------------------------------------------------------------------------------------
    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'hello' => [
                'description' => 'Contoh hello world',
                'type' => Types::string(),
                'resolve' => function() {
                    return 'Hello World';
                }
            ],
            'user' => [
                'description' => 'Data user berdasarkan ID',
                'type' => Types::user(),
                'args' => [
                    'id' => Types::nonNull(Types::int())
                ],
                'resolve' => function($rootValue, $args, $context) {
                    $pdo = $context['pdo'];
                   
                    $id = $args['id'];
                    
                    $result = $pdo->query("select * from users where id = {$id}");
                    return $result->fetchObject() ?: null;
                }
            ],
            'product' => [
                'description' => 'Data produk berdasarkan ID',
                'type' => Types::product(),
                'args' => [
                    'id' => Types::nonNull(Types::int())
                ],
                'resolve' => function($rootValue, $args, $context) {
                    $pdo = $context['pdo'];
                    $id = $args['id'];
                    $result = $pdo->query("select * from products where id = {$id}");
                    return $result->fetchObject() ?: null;
                }
            ],
            'products' => [
                'description' => 'Data list produk',
                'type' => Types::listOf(Types::product()),
                'args' => [
                    
                    'offset' => Types::int(),
                    'limit' => Types::int()
                ],
                'resolve' => function($rootValue, $args, $context) {
                    $pdo = $context['pdo'];
                  
                    $limit = $args['limit'] ?: 10;
                    $offset = $args['offset'] ?: 0;

                    if ($limit > 50) $limit = 50;

                    $result = $pdo->query("select * from products order by id desc limit {$limit} offset {$offset}");
                    return $result->fetchAll(PDO::FETCH_OBJ);
                }
            ],
            'productCategories' => [
                'description' => 'Data list kategori produk',
                'type' => Types::listOf(Types::productCategory()),
                'resolve' => function($rootValue, $args, $context) {
                    $pdo = $context['pdo'];
                    $result = $pdo->query("select * from product_categories order by name asc");
                    return $result->fetchAll(PDO::FETCH_OBJ);
                }
            ],
        ]
    ]);


    // #Membuat Skema
    // -----------------------------------------------------------------------------------------------
    $schema = new Schema([
        'query' => $queryType
    ]);

    // Eksekusi GraphQL
    // ===============================================================================================
    $result = GraphQL::execute(
        $schema,
        $data['query'],
        null,
        $appContext,
        (array) $data['variables']
    );

    // Memasukkan Error kedalam $result (kalo ada)
    // ===============================================================================================
    if ($debug && !empty($phpErrors)) {
        $result['extensions']['phpErrors'] = array_map(
            ['GraphQL\Error\FormattedError', 'createFromPHPError'],
            $phpErrors
        );
    }
    $httpStatus = 200;
} catch (\Exception $error) {
    // Handling Exception
    // ===============================================================================================
    $httpStatus = 500;
    if (!empty($_GET['debug'])) {
        $result['extensions']['exception'] = FormattedError::createFromException($error);
    } else {
        $result['errors'] = [FormattedError::create('Unexpected Error')];
    }
}

// #Tampilkan Hasilnya (Berupa JSON)
// ===============================================================================================
header('Content-Type: application/json', true, $httpStatus);
echo json_encode($result);
