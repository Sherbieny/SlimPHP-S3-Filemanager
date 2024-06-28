<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Controller\HomeController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', HomeController::class . ':home');
    $app->get('/loadFolders', HomeController::class . ':loadFolders');
    $app->get('/loadFiles', HomeController::class . ':loadFiles');
    $app->post('/createFolder', HomeController::class . ':createFolder');
    $app->post('/uploadFile', HomeController::class . ':uploadFile');
    $app->delete('/deleteFolder', HomeController::class . ':deleteFolder');
    $app->delete('/deleteFile', HomeController::class . ':deleteFile');
    $app->post('/sortOrder', HomeController::class . ':sortOrder');

    // $app->group('/users', function (Group $group) {
    //     $group->get('', ListUsersAction::class);
    //     $group->get('/{id}', ViewUserAction::class);
    // });
};
