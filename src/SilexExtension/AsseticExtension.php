<?php

namespace SilexExtension;

use Silex\Application,
    Silex\ServiceProviderInterface;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request;

use Assetic\AssetManager,
    Assetic\FilterManager,
    Assetic\AssetWriter,
    Assetic\Asset\AssetCache,
    Assetic\Factory\AssetFactory,
    Assetic\Factory\LazyAssetManager,
    Assetic\Cache\FilesystemCache,
    Assetic\Extension\Twig\AsseticExtension as TwigAsseticExtension;

class AsseticExtension implements ServiceProviderInterface
{

    public function register(Application $app)
    {
        $app['assetic.options'] = array_replace(array(
            'debug' => false,
            'formulae_cache_dir' => null,
        ), isset($app['assetic.options']) ? $app['assetic.options'] : array());

        $options = $app['assetic.options'];

        /**
         * Asset Factory conifguration happens here
         */
        $app['assetic'] = $app->share(function () use ($app) {
            // initializing lazy asset manager
            if (isset($app['assetic.formulae']) &&
               !is_array($app['assetic.formulae']) &&
               !empty($app['assetic.formulae'])
            ) {
                $app['assetic.lazy_asset_manager'];
            }

            return $app['assetic.factory'];
        });

        /**
         * Factory
         * @return Assetic\Factory\AssetFactory
         */
        $app['assetic.factory'] = $app->share(function() use ($app) {
            $options = $app['assetic.options'];
            $factory = new AssetFactory($app['assetic.asset_path'], $options['debug']);
            $factory->setAssetManager($app['assetic.asset_manager']);
            $factory->setFilterManager($app['assetic.filter_manager']);

            return $factory;
        });

        /**
         * Writes down all lazy asset manager and asset managers assets
         */
        $app->after(function() use ($app) {
            $app['assetic.asset_writer']->writeManagerAssets(
                $app['assetic.lazy_asset_manager']);
            $app['assetic.asset_writer']->writeManagerAssets(
                $app['assetic.asset_manager']);
        });

        /**
         * Asset writer, writes to the 'assetic.path_to_web' folder
         */
        $app['assetic.asset_writer'] = $app->share(function () use ($app) {
            return new AssetWriter($app['assetic.path_to_web']);
        });

        /**
         * Asset manager, can be accessed via $app['assetic.asset_manager']
         * and can be configured via $app['assetic.assets'], just provide a
         * protected callback $app->protect(function($am) { }) and add
         * your assets inside the function to asset manager ($am->set())
         */
        $app['assetic.asset_manager'] = $app->share(function () use ($app) {
            $assets = isset($app['assetic.assets']) ? $app['assetic.assets'] : function() {};
            $manager = new AssetManager();

            call_user_func_array($assets, array($manager, $app['assetic.filter_manager']));
            return $manager;
        });

        /**
         * Filter manager, can be accessed via $app['assetic.filter_manager']
         * and can be configured via $app['assetic.filters'], just provide a
         * protected callback $app->protect(function($fm) { }) and add
         * your filters inside the function to filter manager ($fm->set())
         */
        $app['assetic.filter_manager'] = $app->share(function () use ($app) {
            $filters = isset($app['assetic.filters']) ? $app['assetic.filters'] : function() {};
            $manager = new FilterManager();

            call_user_func_array($filters, array($manager));
            return $manager;
        });

        $app['twig']->addExtension(new TwigAsseticExtension($app['assetic.factory']));

        /**
         * Lazy asset manager for loading assets from Twig templates
         */
        $app['assetic.lazy_asset_manager'] = $app->share(function () use ($app) {
            if (!isset($app['twig'])) {
                return null;
            }

            $options  = $app['assetic.options'];
            $lazy     = new LazyAssetmanager($app['assetic.factory']);

            $lazy->setLoader('twig', new \Assetic\Extension\Twig\TwigFormulaLoader($app['twig']));

            foreach($app['twig.path'] as $path) {
                $lazy->addResource(new AsseticExtension\DirectoryResource($app['twig']->getLoader(), $path), 'twig');
            }

            return $lazy;
        });

        $addAssetRoute = function(Application $app, $asset, $name, $pos = null) {
            $pattern = $asset->getTargetPath();

            $route = '_assetic_'.$name;
            if (null !== $pos) {
                $route .= '_'.$pos;
            }

            $format = pathinfo($pattern, PATHINFO_EXTENSION);

            $app->get($pattern, function(Request $request) use($app, $asset, $name, $format) {
                $options = $app['assetic.options'];

                $response = new Response();
                $response->setExpires(new \DateTime());

                switch($format) {
                    case 'js':
                        $response->headers->set('Content-Type', 'application/javascript');
                        break;
                    case 'css':
                        $response->headers->set('Content-Type', 'text/css');
                        break;
                }

                if (null !== $lastModified = $asset->getLastModified()) {
                    $date = new \DateTime();
                    $date->setTimestamp($lastModified);
                    $response->setLastModified($date);
                }

                if ($app['assetic.lazy_asset_manager']->hasFormula($name)) {
                    $formula = $app['assetic.lazy_asset_manager']->getFormula($name);
                    $formula['last_modified'] = $lastModified;
                    $response->setETag(md5(serialize($formula)));
                }

                if ($response->isNotModified($request)) {
                    return $response;
                }
                $cache = new AssetCache($asset, new FilesystemCache($options['formulae_cache_dir']));

                $response->setContent($cache->dump());

                return $response;
            })->bind($route);
        };

        foreach($app['assetic.lazy_asset_manager']->getNames() as $name) {
            $asset = $app['assetic.lazy_asset_manager']->get($name);

            $addAssetRoute($app, $asset, $name);

            if ($options['debug']) {
                $i = 0;
                foreach($asset as $leaf) {
                    $addAssetRoute($app, $leaf, $name, $i++);
                }
            }
        }

        // autoloading the assetic library
        if (isset($app['assetic.class_path'])) {
            $app['autoloader']->registerNamespace('Assetic', $app['assetic.class_path']);
        }
    }
}
