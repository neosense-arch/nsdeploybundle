<?php

namespace NS\DeployBundle\Model;

class Backup
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $dir;

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $dir
     */
    public function setDir($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }
}
