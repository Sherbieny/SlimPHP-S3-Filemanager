<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Controller\HomeController;
use App\Service\S3Service;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        // Add more definitions here

        //Services

        //S3Service
        S3Service::class => function (ContainerInterface $c) {
            return new App\Service\S3Service();
        },

        //Controllers

        //HomeController
        HomeController::class => function (ContainerInterface $c) {
            $logger = $c->get(LoggerInterface::class);
            $s3Service = $c->get(S3Service::class);
            $session = $c->get('session');
            return new HomeController(
                $logger,
                $c->get('view'),
                $s3Service,
                $session
            );
        },

    ]);
};
