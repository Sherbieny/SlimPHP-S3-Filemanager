<?php

namespace App\Service;

use Aws\S3\S3Client;

class S3Service
{
    /**
     * @var string $bucket
     */
    private $bucket;

    /**
     * @var S3Client $s3Client
     */
    private $s3Client;

    public function __construct()
    {
        $this->setBucket();
        $this->setClient();
    }

    public function setBucket()
    {
        $this->bucket = $_ENV['S3_BUCKET'];
    }

    public function setClient()
    {
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['S3_DEFAULT_REGION'],
            'credentials' => [
                'key' => $_ENV['S3_ACCESS_KEY_ID'],
                'secret' => $_ENV['S3_SECRET_ACCESS_KEY']
            ]
        ]);
    }

    public function getBucket()
    {
        return $this->bucket;
    }

    public function getClient()
    {
        return $this->s3Client;
    }

    public function getBaseS3Url()
    {
        return 'https://' . $this->getBucket() . '.s3.amazonaws.com/';
    }

    public function getRootFolderPath()
    {
        return $_ENV['S3_ROOT_FOLDER'];
    }

    /**
     * Upload file content without saving file on server
     *
     * @param string $fileName
     * @param string $content
     * @param array  $meta
     * @param string $privacy
     * @return string file url
     */
    public function upload($fileName, $content, array $options = [], $privacy = 'public-read')
    {
        return $this->getClient()->upload($this->getBucket(), $fileName, $content, $privacy, $options)->toArray()['ObjectURL'];
    }

    /**
     * Upload existing file content
     * handle both local and remote files
     *
     * @param string       $fileUrl
     * @param string|null  $newFilename
     * @param array        $meta
     * @param string       $privacy
     * @return string file url
     */
    public function uploadFile($fileOrUrl, $newFilename = null, array $meta = [], $privacy = 'public-read')
    {
        if (filter_var($fileOrUrl, FILTER_VALIDATE_URL)) {
            $fileContents = file_get_contents($fileOrUrl);
            if ($fileContents === false) {
                throw new \Exception("Failed to download file: " . $fileOrUrl);
            }

            if (!$newFilename) {
                $newFilename = basename($fileOrUrl);
            }

            if (!isset($meta['contentType'])) {
                $meta['contentType'] = get_headers($fileOrUrl, 1)["Content-Type"];
            }
        } elseif ($fileOrUrl instanceof \Slim\Psr7\UploadedFile) {
            $fileContents = $fileOrUrl->getStream()->getContents();
            if (!$newFilename) {
                $newFilename = $fileOrUrl->getClientFilename();
            }

            if (!isset($meta['contentType'])) {
                $meta['contentType'] = $fileOrUrl->getClientMediaType();
            }
        } else {
            // Assuming $fileOrUrl is a file path
            $fileContents = file_get_contents($fileOrUrl);
            if ($fileContents === false) {
                throw new \Exception("Failed to read uploaded file.");
            }

            if (!$newFilename) {
                $newFilename = basename($fileOrUrl);
            }

            if (!isset($meta['contentType'])) {
                $meta['contentType'] = mime_content_type($fileOrUrl);
            }
        }

        return $this->upload($newFilename, $fileContents, $meta, $privacy);
    }

    /**
     * Create a folder in the AWS S3 bucket
     *
     * @param string $folderPath
     * @return bool
     */
    public function createAWSFolder($folderPath)
    {
        $result = $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $folderPath . '/',
            'Body' => '',
        ]);

        return isset($result['@metadata']['statusCode']) && $result['@metadata']['statusCode'] === 200;
    }

    /**
     * Delete a file from the AWS S3 bucket
     *
     * @param string $filePath
     * @param bool   $isFolder
     * @return bool
     */
    public function deleteFile($filePath, $isFolder = false)
    {
        $filePath = urldecode($filePath);
        //remove the first slash
        $filePath = ltrim($filePath, '/');

        if ($isFolder) {
            $this->getClient()->deleteMatchingObjects($this->getBucket(), $filePath);

            return true;
        }

        $result = $this->getClient()->deleteObject([
            'Bucket' => $this->getBucket(),
            'Key' => $filePath,
        ]);

        return isset($result['@metadata']['statusCode']) && $result['@metadata']['statusCode'] === 204;
    }

    /**
     * Get file data from S3, filekey value can be the file path in bucket or full url
     *
     * @param string $fileKey
     * @return mixed
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function getFile(string $fileKey)
    {
        if (empty($fileKey)) {
            return "";
        }

        // Replace backslashes with forward slashes
        $fileKey = str_replace('\\', '/', $fileKey);

        $parsedUrl = parse_url($fileKey);

        if (isset($parsedUrl['host']) && strpos($parsedUrl['host'], 's3') !== false && strpos($parsedUrl['host'], 'amazonaws.com') !== false) {
            $fileKey = ltrim($parsedUrl['path'], '/');
        }

        //decode url to get the real file path
        //$fileKey = urldecode($fileKey);

        return $this->getClient()->getObject(['Bucket' => $this->getBucket(), 'Key' => $fileKey])->get('Body');
    }

    /**
     * Get multiple files urls from s3 using the files keys, file keys can be a relative path or full s3 url
     * @param array $filesKeys
     * @return array
     */
    public function getFiles(array $filesKeys)
    {
        $files = [];
        foreach ($filesKeys as $fileKey) {
            $filename = basename($fileKey);
            $files[$filename] = $this->getFile($fileKey);
        }

        return $files;
    }

    // get folder files count
    /**
     * Get the number of files in a folder
     *
     * @param string $folderPath
     * @return int
     */
    public function getFolderFilesCount($folderPath)
    {
        $objects = $this->getClient()->listObjects([
            'Bucket' => $this->getBucket(),
            'Prefix' => $folderPath,
        ]);

        return count($objects['Contents']);
    }

    // getobjecttagging
    /**
     * Get object tagging data from S3
     */
    public function getObjectTagging($fileKey)
    {
        $result = $this->getClient()->getObjectTagging([
            'Bucket' => $this->getBucket(),
            'Key' => $fileKey,
        ]);

        if (isset($result['TagSet']) && is_array($result['TagSet'])) {
            return $result['TagSet'];
        }

        return [];
    }

    /**
     * Get all folders inside a folder
     *
     * @param string $folderKey
     * @return mixed
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function getFolders(string $folderKey, array &$data = [])
    {
        if (empty($folderKey)) {
            return [];
        }

        $delimiter = '/';
        $result = $this->getClient()->listObjects([
            'Bucket' => $this->getBucket(),
            'Prefix' => $folderKey,
            'Delimiter' => $delimiter,
        ]);

        if (isset($result['CommonPrefixes'])) {
            foreach ($result['CommonPrefixes'] as $commonPrefix) {
                $prefix = $commonPrefix['Prefix'];
                $data[$prefix] = $prefix;
                $this->getFolders($prefix, $data);
            }
        }

        return $this->buildDirTree($data);
    }

    private function buildDirTree($foldersPaths)
    {
        $folders = [];
        $rootPath = $this->getRootFolderPath() . '/';
        foreach ($foldersPaths as $path => $relativePath) {
            // Removing the root path to get the relative path
            $relativePath = str_replace($rootPath, '', $relativePath);

            // Exploding by "/"
            $parts = explode('/', $relativePath);

            // If the path has more than one parts, it's not a root level folder
            if (count($parts) > 1) {
                $root = array_shift($parts); // Take the root level folder name
                if (!array_key_exists($root, $folders)) {
                    $folders[$root] = $this->createFolder($root, $rootPath . $root);
                }
                $cur = &$folders[$root]['children']; // Add the rest under the root folder
            } else {
                $cur = &$folders;
            }

            foreach ($parts as $part) {
                if (!empty($part)) {  // This will ensure we don't create a folder with an empty key
                    if (!key_exists($part, $cur)) {
                        $cur[$part] = $this->createFolder($part, $path);
                    }
                    $cur = &$cur[$part]['children'];
                }
            }
            unset($cur);
        }

        return $folders;
    }

    private function createFolder($name, $path)
    {
        $uniqueId = 'a' . bin2hex(random_bytes(4)); // This will generate a unique ID not starting with a zero
        return ['id' => $uniqueId, 'name' => $name, 'path' => $path, 'children' => []];
    }

    public function countFolders(array $folders): int
    {
        $count = 0;

        foreach ($folders as $folder) {
            $count++; // count the current folder
            if (isset($folder['children']) && is_array($folder['children'])) {
                $count += $this->countFolders($folder['children']);
            }
        }

        return $count;
    }

    /**
     * Get all object keys in a given bucket directory.
     *
     * @param string $prefix
     * @return array
     */
    public function getObjectKeys(string $bucketDirectory): array
    {
        $objects = $this->getClient()->listObjectsV2([
            'Bucket' => $this->getBucket(),
            'Prefix' => $bucketDirectory
        ]);

        $keys = [];
        do {
            foreach ($objects['Contents'] as $object) {
                $filenamePath = str_replace($bucketDirectory, '', $object['Key']);
                $keys[] = $filenamePath;
            }

            // Check if there are more than 1000 objects.
            if (isset($objects['NextContinuationToken'])) {
                $objects = $this->getClient()->listObjectsV2([
                    'Bucket' => $this->getBucket(),
                    'Prefix' => $bucketDirectory,
                    'ContinuationToken' => $objects['NextContinuationToken']
                ]);
            } else {
                $objects = [];
            }
        } while (!empty($objects));

        return $keys;
    }

    /**
     * Get all files in a s3 directory.
     *
     * @param string $prefix
     * @return array
     */
    public function getAssetFiles(string $prefix): array
    {
        $objects = $this->getClient()->listObjectsV2([
            'Bucket' => $this->getBucket(),
            'Prefix' => $prefix
        ]);

        $keys = [];
        do {
            foreach ($objects['Contents'] as $object) {

                //skip folders - ends with /
                if (substr($object['Key'], -1) === '/') {
                    continue;
                }

                // build the file path, aws url + image full path
                $filenamePath = $this->getBaseS3Url() . $object['Key'];
                $sortOrder = $this->getSortOrder($object['Key']);

                //if svg folder, get the file content and the url
                if (strpos($object['Key'], 'svg') !== false) {
                    $file = $this->getFile($object['Key']);
                    if ($file instanceof \GuzzleHttp\Psr7\Stream) {
                        $keys[] = [
                            'url' => $filenamePath,
                            'content' => $file->getContents(),
                            'sort_order' => $sortOrder,
                            'path' => $object['Key']
                        ];
                    }
                    continue;
                }



                $keys[] = ['url' => $filenamePath, 'sort_order' => $sortOrder, 'path' => $object['Key']];
            }

            // Check if there are more than 1000 objects.
            if (isset($objects['NextContinuationToken'])) {
                $objects = $this->getClient()->listObjectsV2([
                    'Bucket' => $this->getBucket(),
                    'Prefix' => $prefix,
                    'ContinuationToken' => $objects['NextContinuationToken']
                ]);
            } else {
                $objects = [];
            }
        } while (!empty($objects));


        //sort the files based on the sort order
        usort($keys, function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $keys;
    }

    /**
     * Get sort_order tag data from a file in the S3 bucket.
     *
     * @param string $filePath
     * @return int
     */
    public function getSortOrder($filePath)
    {
        $tags = $this->getObjectTagging($filePath);

        foreach ($tags as $tag) {
            if ($tag['Key'] === 'sort_order') {
                return (int)$tag['Value'];
            }
        }

        return 0;
    }

    /**
     * Add sort_order tag data to all files with a given folder based on the default sort order of the files in the folder.
     *
     * @param string $folderPath
     * @return bool
     */
    public function addSortOrderTag($folderPath)
    {
        $files = $this->getObjectKeys($folderPath);
        $files = array_filter($files, function ($file) {
            // Remove folders from the list of files and empty strings
            return !empty($file) && strpos($file, '/') === false;
        });

        $files = array_values($files);

        $filesCount = count($files);

        for ($i = 0; $i < $filesCount; $i++) {
            $filePath = $folderPath . $files[$i];
            //using putObjectTagging
            try {
                $result = $this->getClient()->putObjectTagging([
                    'Bucket' => $this->getBucket(),
                    'Key' => $filePath,
                    'Tagging' => [
                        'TagSet' => [
                            [
                                'Key' => 'sort_order',
                                'Value' => $i + 1,
                            ],
                        ],
                    ],
                ]);
            } catch (\Exception $e) {
                return false;
            }

            if (!(isset($result['@metadata']['statusCode']) && $result['@metadata']['statusCode'] === 200) && $result['versionId'] !== 'null') {
                return false;
            }
        }

        return true;
    }

    /**
     * Set sort_order tag data to a file in the S3 bucket.
     *
     * @param array $sortingData
     * @return bool
     */
    public function setSortOrderTag(array $sortingData)
    {
        foreach ($sortingData as $filePath => $sortOrder) {
            $filePath = urldecode($filePath);
            //remove the first slash
            $filePath = ltrim($filePath, '/');

            $result = $this->getClient()->putObjectTagging([
                'Bucket' => $this->getBucket(),
                'Key' => $filePath,
                'Tagging' => [
                    'TagSet' => [
                        [
                            'Key' => 'sort_order',
                            'Value' => $sortOrder,
                        ],
                    ],
                ],
            ]);

            if (!(isset($result['@metadata']['statusCode']) && $result['@metadata']['statusCode'] === 200 && $result['versionId'] !== 'null')) {
                return false;
            }
        }

        return true;
    }
}
