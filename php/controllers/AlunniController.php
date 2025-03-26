<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AlunniController
{
  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM alunni");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }


  public function show(Request $request, Response $response, $args){
    $response->getBody()->write("ciao ". $args["id"]);
    return $response;
  }



  public function destroy(Request $request, Response $response, $args){
    $response->getBody()->write("ciao");
    return $response;
  }

  public function create(Request $request, Response $response, $args){
    
    $body = json_decode($request->getBody()->getContents(), true);    
    $nome = $body["nome"];
    $cognome = $body["cognome"];

    $response->getBody()->write($cognome);
    return $response;
  }

}
