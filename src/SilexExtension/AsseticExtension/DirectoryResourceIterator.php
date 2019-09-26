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

use Assetic\Extension\Twig\TwigResource;

class DirectoryResourceIterator extends \RecursiveIteratorIterator
{
    protected $loader;
    protected $path;

    /**
     * Constructor.
     *
     * @param LoaderInterface   $loader   The templating loader
     * @param string            $path     The directory
     * @param RecursiveIterator $iterator The inner iterator
     */
    public function __construct(\Twig_LoaderInterface $loader, $path, \RecursiveIterator $iterator)
    {
        $this->loader = $loader;
        $this->path = $path;

        parent::__construct($iterator);
    }

    public function current()
    {
        $file = parent::current();

        return new TwigResource($this->loader, $file->getBasename());
    }
}
