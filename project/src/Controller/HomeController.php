<?php

namespace App\Controller;

use App\Service\S3Service;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use SlimSession\Helper;

class HomeController
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Twig $view
     */
    private $view;

    /**
     * @var S3Service $s3Service
     */
    private $s3Service;

    /**
     * @var Helper $session
     */
    private $session;

    public function __construct(
        LoggerInterface $logger,
        Twig $view,
        S3Service $s3Service,
        Helper $session
    ) {
        $this->logger = $logger;
        $this->view = $view;
        $this->s3Service = $s3Service;
        $this->session = $session;
    }

    public function home(Request $request, Response $response, $args)
    {

        try {
            $rootFolderPath = $this->s3Service->getRootFolderPath();
            $folders = $this->session->get('folders');
            if (!$folders) {
                $folders = $this->s3Service->getFolders($rootFolderPath);
                $this->session->set('folders', $folders);
            }

            $this->view->render($response, 'home.twig', [
                'folders' => $folders,
                'assets_root_folder_path' =>  $rootFolderPath . '/',
                'error' => empty($rootFolderPath),
                'files' => []
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $this->view->render($response, 'home.twig', [
                'error' => true
            ]);
        }

        return $response;
    }

    //load folders
    public function loadFolders(Request $request, Response $response, $args)
    {

        try {
            $rootFolderPath = $this->s3Service->getRootFolderPath();
            $folders = $this->s3Service->getFolders($rootFolderPath);
            if (!$folders) {
                throw new \Exception('No folders found');
            }
            $this->session->set('folders', $folders);
            return $this->view->render($response, 'snippets/folders.twig', [
                'folders' => $folders,
                'assets_root_folder_path' =>  $rootFolderPath . '/',
                'error' => empty($rootFolderPath)
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->view->render($response, 'snippets/folders.twig', [
                'error' => true
            ]);
        }
    }

    public function loadFiles(Request $request, Response $response, $args)
    {

        try {
            $folderPath = $request->getQueryParams()['path'];
            if (!$folderPath) {
                throw new \Exception('Folder path is required');
            }


            $files = $this->s3Service->getAssetFiles($folderPath);

            if (empty($files)) {
                throw new \Exception('No files found');
            }

            return $this->view->render($response, 'snippets/files.twig', [
                'files' => $files,
                'assets_root_folder_path' =>  $folderPath . '/',
                'error' => empty($folderPath)
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->view->render($response, 'snippets/files.twig', [
                'error' => true,
                'files' => []
            ]);
        }
    }

    public function createFolder(Request $request, Response $response, $args)
    {
        try {

            $folderPath = $request->getParsedBody()['path'];

            if (!$folderPath) {
                throw new \Exception('Folder name and path are required');
            }

            $result = $this->s3Service->createAWSFolder($folderPath);
            if (!$result) {
                throw new \Exception('Failed to create folder');
            }

            $payload = json_encode([
                'success' => true,
                'message' => 'Folder created successfully'
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $payload = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function uploadFile(Request $request, Response $response, $args)
    {
        try {

            $uploadedFiles = $request->getUploadedFiles();
            $uploadedFile = $uploadedFiles['file'] ?? null;

            if (!$uploadedFile) {
                throw new \Exception('File is required');
            }

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                throw new \Exception('Failed to upload file');
            }

            $folderPath = $request->getParsedBody()['path'];
            if (!$folderPath) {
                throw new \Exception('Folder path is required');
            }

            $result = $this->s3Service->uploadFile($uploadedFile, $folderPath . $uploadedFile->getClientFilename());
            if (!$result) {
                throw new \Exception('Failed to upload file');
            }

            $payload = json_encode([
                'success' => true,
                'message' => 'File uploaded successfully'
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $payload = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function deleteFile(Request $request, Response $response, $args)
    {
        try {

            $filePath = $request->getParsedBody()['path'];
            if (!$filePath) {
                throw new \Exception('File path is required');
            }

            $this->logger->info('Deleting file: ' . $filePath);

            $result = $this->s3Service->deleteFile($filePath);
            $this->logger->info('Delete file result: ' . $result);

            if (!$result) {
                throw new \Exception('Failed to delete file');
            }

            $payload = json_encode([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $payload = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function deleteFolder(Request $request, Response $response, $args)
    {
        try {

            $folderPath = $request->getParsedBody()['path'];
            if (!$folderPath) {
                throw new \Exception('Folder path is required');
            }

            $result = $this->s3Service->deleteFile($folderPath, true);
            if (!$result) {
                throw new \Exception('Failed to delete folder');
            }

            $payload = json_encode([
                'success' => true,
                'message' => 'Folder deleted successfully'
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $payload = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    //sortOrder
    public function sortOrder(Request $request, Response $response, $args)
    {
        try {

            $sortingData = json_decode($request->getParsedBody()['sortingData'], true);
            if (!$sortingData) {
                throw new \Exception('Sorting data is required');
            }

            $result = $this->s3Service->setSortOrderTag($sortingData);

            if (!$result) {
                throw new \Exception('Failed to sort order');
            }

            $payload = json_encode([
                'success' => true,
                'message' => 'Sort order updated successfully'
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $payload = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
}
