<?php
/**
 * EntryEvents plugin for Craft CMS 3.x
 *
 * A plugin which triggers on entry events
 *
 * @link      jasonbradley.co
 * @copyright Copyright (c) 2019 Jason Bradley
 */

namespace jasonbradley\entryevents;


use Craft;
use craft\elements\Entry;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

use craft\events\ElementEvent;
use craft\services\Elements;


/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Jason Bradley
 * @package   EntryEvents
 * @since     1.0.0
 *
 */
class EntryEvents extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * EntryEvents::$plugin
     *
     * @var EntryEvents
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * EntryEvents::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $fileTarget = new \craft\log\FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/' . EntryEvents::getName() . '.log'), // <--- path of the log file
            'categories' => ['your-plugin-handle'] // <--- categories in the file
        ]);
        // include the new target file target to the dispatcher
        Craft::getLogger()->dispatcher->targets[] = $fileTarget;

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if ($event->element instanceof craft\elements\Entry) {

                    // set entry
                    $entry = $event->element;

                    // If element is not live, return
                    if (!$this->shouldBustCache($entry)) return;

                    // If no uri, return
                    if (is_null($entry['uri'])) return;

                    //Determine cache key
                    $siteId = Craft::$app->getSites()->getCurrentSite()->id;

                    $cacheKey = 'elementapi:' . $siteId . ':' . "api/v1/" . $entry['uri'] . ':' . "";

                    // Logging
                    EntryEvents::log(Craft::$app->getSites()->getCurrentSite()->name);
                    EntryEvents::log($entry['uri']);
                        //Delete cache
                    $cacheService = Craft::$app->getCache();
                    $cacheService->delete($cacheKey);

//                     Api call for element
                    $client = new \GuzzleHttp\Client();
                    $uri = Craft::$app->getSites()->getCurrentSite()->name . '/api/v1/' . $entry['uri'];

                    $res = $client->get($uri, array(
                        'content-type' => 'application/json'
                    ));
                }
            }
        );


        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'entry-events',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================
    private static function log($message)
    {
        Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, 'your-plugin-handle');
    }

    private function getName()
    {
        $path = explode('\\', __CLASS__);
        return array_pop($path);
    }

    /**
     * @param Entry $entry
     *
     * @return bool
     */
    protected function shouldBustCache(Entry $entry): bool
    {
        $bustCache = true;

        // Only bust the cache if the element is LIVE
        if ($entry->getStatus() !== Entry::STATUS_LIVE) {
            $bustCache = false;
        }

        return $bustCache;
    }

}
