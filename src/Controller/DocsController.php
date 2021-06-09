<?php

namespace HarmBandstra\SwaggerUiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

class DocsController extends AbstractController
{
    /** @var array */
    private $swaggerFiles;

    /** @var string */
    private $directory;

    /** @var string */
    private $assetUrlPath;

    /** @var string|null */
    private $configFile;

    public function __construct($swaggerFiles, $directory, $assetUrlPath, $configFile)
    {
        $this->swaggerFiles = $swaggerFiles;
        $this->directory = $directory;
        $this->assetUrlPath = $assetUrlPath;
        $this->configFile = $configFile;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        if (!$request->get('url')) {
            // if there is no ?url=... parameter, redirect to the default one
            $defaultSwaggerFile = reset($this->swaggerFiles);

            return $this->redirect($this->getRedirectUrlToSpec($defaultSwaggerFile));
        }
        $contents = @file_get_contents(__DIR__ . '/../Resources/public/index.html');
        if ($contents === false) {
            return new Response(
                'Unable to load [Resources/public/index.html]. Did [ScriptHandler::linkAssets] run correctly?',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new Response($contents);
    }

    /**
     * @param string $fileName
     *
     * @return RedirectResponse
     */
    public function redirectAction($fileName)
    {
        // redirect to swagger file if that's what we're looking for
        if (in_array($fileName, $this->swaggerFiles, true)) {
            return $this->redirect($this->getRedirectUrlToSpec($fileName));
        }

        // redirect to the assets dir so that relative links work
        return $this->redirect($this->assetUrlPath . $fileName);
    }

    /**
     * @param string $fileName
     *
     * @return JsonResponse|Response
     */
    public function swaggerFileAction($fileName)
    {
        try {
            $filePath = $this->getFilePath($fileName);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'yml' || $extension === 'yaml') {
            $fileContents = Yaml::parse(file_get_contents($filePath));

            return new JsonResponse($fileContents);
        }

        $fileContents = file_get_contents($filePath);

        return new Response(
            $fileContents,
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    private function getFilePath($fileName = '')
    {
        if ($this->configFile !== $fileName) {
            if ($fileName !== '' && !in_array($fileName, $this->swaggerFiles)) {
                throw new \RuntimeException(
                    sprintf('File [%s] not defined under [hb_swagger_ui.files] in config.yml.', $fileName)
                );
            }
        }

        if ($this->directory === '') {
            throw new \RuntimeException(
                'Directory [hb_swagger_ui.directory] not defined or empty in config.yml.'
            );
        }

        $filePath = realpath($this->directory . DIRECTORY_SEPARATOR . $fileName);
        if (!is_file($filePath)) {
            throw new FileNotFoundException(sprintf('File [%s] not found.', $fileName));
        }

        return $filePath;
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    private function getRedirectUrlToSpec($fileName)
    {
        if (strpos($fileName, '/') === 0 || preg_match('#http[s]?://#', $fileName)) {
            // if absolute path or URL use it raw
            $specUrl = $fileName;
        } else {
            $specUrl = $this->generateUrl(
                'hb_swagger_ui_swagger_file',
                ['fileName' => $fileName],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        $parameters = ['url' => $specUrl];
        if ($this->configFile) {
            $parameters['configUrl'] = $this->generateUrl(
                'hb_swagger_ui_swagger_file',
                ['fileName' => $this->configFile],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        return $this->generateUrl('hb_swagger_ui_default', $parameters);
    }
}
