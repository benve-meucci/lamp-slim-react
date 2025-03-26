<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AlunniController.php';

$app = AppFactory::create();

// curl http://localhost:8080/alunni
$app->get('/alunni', "AlunniController:index");

// curl http://localhost:8080/alunni/2
$app->get('/alunni/{id:\d+}', "AlunniController:show");


// curl -X POST http://locahost:8080/alunni -H "Content-Type: application/json" -d '{"nome": "ciccio", "cognome": "bello"}'
$app->post('/alunni', "AlunniController:create");


// curl -X PUT http://locahost:8080/alunni/2 -H "Content-Type: application/json" -d '{"nome": "ciccio", "cognome": "bello"}'
$app->put('/alunni/{id}', "AlunniController:update");

// curl -X DELETE http://localhost:8080/alunni/2
$app->delete('/alunni/{id}', "AlunniController:destroy");




$app->run();
