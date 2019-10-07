<?php
/**
 * Created by PhpStorm.
 * User: Joel
 * Date: 12/20/2017
 * Time: 12:58 AM
 */

namespace App;


use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ChromeDriver
{
    const GENERAL_SESSION_NAME = "general";
    public $host;

    public function __construct()
    {
        $this->host = env('CHROME_DRIVER_HOST', 'http://selenium:4444/wd/hub');
    }

    private function getSessionIdFilePath()
    {
        return storage_path(
            env("SESSION_FILE_PATH",'sessions/session_file.txt')
        );
    }

    /**
     * @param bool        $restoreSession
     * @param bool        $sessionName
     * @param null|string $proxy
     * @param bool|null   $isHeadless Defines, if chrome will be run in a headless mode or with VNC.
     *
     * @return RemoteWebDriver
     */
    public function getDriver(
        $restoreSession = false,
        $sessionName = false,
        ?string $proxy = null,
        ?bool $isHeadless = true
    )
    {
        $sessionName = $sessionName ? $sessionName : self::GENERAL_SESSION_NAME;

        if ($isHeadless) {
            $this->host = env('CHROME_DRIVER_HEADLESS_HOST', 'http://selenium-headless:4444/wd/hub');
        }

        if ($restoreSession) {
            $driver = RemoteWebDriver::createBySessionID(
                $this->getLastSessionId($sessionName),
                $this->host,
                60000,
                60000
            );
            try {
                $window = $driver->getWindowHandle();
                $driver->switchTo()->activeElement();
            } catch (\Exception $e) { //WebDriverException
                Log::info('Error switching to existing chrome window.');
                //todo: close the old window with this session - it will stay stale forever
                $driver = $this->startNewSession($sessionName, $proxy);
            }
        } else {
            $driver = $this->startNewSession($sessionName, $proxy);
        }

        return $driver;
    }

    private function startNewSession($sessionName, ?string $proxy): RemoteWebDriver
    {
        $options = new ChromeOptions();
        $prefs   = [
            'download.default_directory' => config('selenium.downloadDirectory'),
        ];

        $options->setExperimentalOption('prefs', $prefs);
        $options->addArguments([
            '--window-size=1500,1200',
            /*'start-maximized',
            'user-data-dir=' . storage_path('chrome_profiles')*/
        ]);

        if ($proxy) {
            $pluginForProxyLogin = '/tmp/a' . uniqid('', true) . '.zip';

            $zip = new ZipArchive();
            $zip->open($pluginForProxyLogin, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile(storage_path('proxy/manifest.json'), 'manifest.json');
            $background = file_get_contents(storage_path('proxy/background.js'));
            $background = str_replace(
                ['%proxy_host', '%proxy_port', '%username', '%password'],
                explode(':', $proxy),
                $background
            );
            $zip->addFromString('background.js', $background);
            $zip->close();

            $options->addExtensions([$pluginForProxyLogin]);
        }

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create($this->host, $capabilities, 60000, 60000);
        $this->storeSessionId($driver->getSessionID(), $sessionName);
        return $driver;
    }

    private function storeSessionId($sessionId, $sessionName)
    {
        // Save webdriver session id to file
        $sessionsData               = $this->getSessionsData();
        $sessionsData[$sessionName] = $sessionId;
        $this->setSessionsData($sessionsData);
    }

    private function getLastSessionId($sessionName)
    {
        $data      = $this->getSessionsData();
        $sessionId = isset($data[$sessionName]) ? $data[$sessionName] : false;
        return $sessionId;
    }

    private function getSessionsData()
    {
        return unserialize(file_get_contents($this->getSessionIdFilePath()));
    }

    private function setSessionsData($data)
    {
        file_put_contents($this->getSessionIdFilePath(),
            serialize($data)
        );
    }


}
