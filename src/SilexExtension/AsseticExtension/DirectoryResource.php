<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SilexExtension\AsseticExtension;

use Assetic\Factory\Resource\DirectoryResource as BaseDirectoryResource;

/**
 * A directory resource that creates Symfony2 templating resources.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class DirectoryResource extends BaseDirectoryResource
{
    protected $loader;
    protected $path;

    /**
     * Constructor.
     *
     * @param LoaderInterface $loader  The templating loader
     * @param string          $path    The directory path
     * @param string          $pattern A regex pattern for file basenames
     */
    public function __construct(\Twig_LoaderInterface $loader, $path, $pattern = null)
    {
        $this->loader = $loader;
        $this->path = rtrim($path, '/').'/';

        parent::__construct($path, $pattern);
    }

    public function getIterator()
    {
        return is_dir($this->path)
            ? new DirectoryResourceIterator($this->loader, $this->path, $this->getInnerIterator())
            : new \EmptyIterator();
    }
}
