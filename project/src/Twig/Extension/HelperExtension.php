<?php

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class HelperExtension extends AbstractExtension
{

    public function getFunctions()
    {
        return [
            new TwigFunction('renderFileContent', [$this, 'renderFileContent'])
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('filename', [$this, 'getFileName']),
        ];
    }

    public function renderFileContent($file)
    {
        $fileContent = '';
        $url = $file['url'];
        $content = $file['content'] ?? '';
        if (substr($url, -4) === '.pdf') {
            $fileContent = '<embed src="' . $url . '" type="application/pdf" width="100%" height="100%" />';
        } else if (substr($url, -4) === '.png' || substr($url, -4) === '.jpg' || substr($url, -5) === '.jpeg' || substr($url, -4) === '.gif') {
            $fileContent = '<img src="' . $url . '" alt="file" width="100%" height="100%" />';
        } else if (substr($url, -4) === '.mp4') {
            $fileContent = '<video width="100%" height="100%" controls>
                <source src="' . $url . '" type="video/mp4">
                Your browser does not support the video tag.
            </video>';
        } else if (substr($url, -4) === '.mp3') {
            $fileContent = '<audio controls>
                <source src="' . $url . '" type="audio/mpeg">
                Your browser does not support the audio tag.
            </audio>';
        } else if (substr($url, -4) === '.svg') {
            $fileContent = '<img src="' . $url . '" alt="file" width="100%" height="100%" style="background-color:grey" />';
        } else {
            $fileContent = '<a href="' . $url . '" target="_blank">View</a>';
        }

        return $fileContent;
    }

    public function getFileName($url)
    {
        $urlArray = explode('/', $url);
        return end($urlArray);
    }
}
